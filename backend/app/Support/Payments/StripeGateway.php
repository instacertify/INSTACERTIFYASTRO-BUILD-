<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Support\PaymentSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout: the payer is sent to a Stripe-hosted page and comes back to /pay when done. */
class StripeGateway extends PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    /** Stripe rejects webhook signatures older than this many seconds, to stop replays. */
    private const TOLERANCE = 300;

    public function configured(): bool
    {
        return PaymentSettings::hasSecret('stripe', 'secretKey');
    }

    public function testMode(): bool
    {
        return str_contains((string) PaymentSettings::secret('stripe', 'secretKey'), '_test_');
    }

    public function checkout(Payment $payment): array
    {
        $return = rtrim(config('instacertify.frontend_url'), '/').'/pay?ref='.$payment->reference;
        $session = $this->http()->asForm()->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->reference,
            'customer_email' => $payment->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $payment->amount,
                    'product_data' => ['name' => $this->description($payment)],
                ],
            ]],
            'metadata' => array_filter(['reference' => $payment->reference, 'invoice' => $payment->invoice_number]),
            'payment_intent_data' => ['description' => $this->description($payment), 'metadata' => ['reference' => $payment->reference]],
            'success_url' => $return,
            'cancel_url' => $return.'&cancelled=1',
        ])->throw()->json();
        $payment->update(['gateway_order_id' => $session['id']]);

        return ['type' => 'stripe', 'url' => $session['url']];
    }

    public function confirm(Payment $payment, array $input): void
    {
        if (blank($payment->gateway_order_id)) {
            return;
        }
        $session = $this->http()->get(self::API.'/checkout/sessions/'.urlencode($payment->gateway_order_id));
        if ($session->successful() && in_array($session->json('payment_status'), ['paid', 'no_payment_required'], true)) {
            $payment->markPaid($session->json('payment_intent'));
        }
    }

    public function hasWebhookSecret(): bool
    {
        return PaymentSettings::hasSecret('stripe', 'webhookSecret');
    }

    /** Stripe-Signature: "t=<timestamp>,v1=<HMAC of "<timestamp>.<body>">", possibly with several v1 values while a secret is rolled. */
    public function validWebhook(Request $request): bool
    {
        $secret = PaymentSettings::secret('stripe', 'webhookSecret');
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            match ($key) {
                't' => $timestamp = $value,
                'v1' => $signatures[] = (string) $value,
                default => null,
            };
        }
        if (blank($secret) || ! ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature));
    }

    public function webhookOutcome(array $event): ?array
    {
        $session = $event['data']['object'] ?? [];
        if (($session['object'] ?? null) !== 'checkout.session' || ! is_string($session['id'] ?? null)) {
            return null;
        }
        $paid = ['order' => $session['id'], 'payment' => $session['payment_intent'] ?? null, 'status' => 'paid', 'reason' => null];

        return match ($event['type'] ?? null) {
            'checkout.session.completed' => ($session['payment_status'] ?? null) === 'paid' ? $paid : null,
            'checkout.session.async_payment_succeeded' => $paid,
            'checkout.session.async_payment_failed' => [...$paid, 'status' => 'failed', 'reason' => 'The bank declined the payment'],
            'checkout.session.expired' => [...$paid, 'status' => 'failed', 'reason' => 'Checkout closed without paying'],
            default => null,
        };
    }

    public function check(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Fill in the secret key first.'];
        }

        return $this->checkResult($this->http()->get(self::API.'/balance')->status());
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson()->withToken((string) PaymentSettings::secret('stripe', 'secretKey'));
    }
}
