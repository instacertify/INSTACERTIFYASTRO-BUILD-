<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Support\PaymentSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Razorpay Orders API + Standard Checkout: the payer pays in Razorpay's pop-up without leaving the page. */
class RazorpayGateway extends PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function configured(): bool
    {
        return filled($this->keyId()) && PaymentSettings::hasSecret('razorpay', 'keySecret');
    }

    public function testMode(): bool
    {
        return str_starts_with((string) $this->keyId(), 'rzp_test_');
    }

    public function checkout(Payment $payment): array
    {
        $order = $this->http()->post(self::API.'/orders', [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'receipt' => $payment->reference,
            'notes' => array_filter([
                'reference' => $payment->reference,
                'invoice' => $payment->invoice_number,
                'company' => Str::limit((string) $payment->company, 250, ''),
            ]),
        ])->throw()->json();
        $payment->update(['gateway_order_id' => $order['id']]);

        return [
            'type' => 'razorpay',
            'key' => $this->keyId(),
            'orderId' => $order['id'],
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'name' => $this->siteName(),
            'description' => $this->description($payment),
            'prefill' => array_filter(['name' => $payment->name, 'email' => $payment->email, 'contact' => $payment->phone]),
        ];
    }

    /** The checkout hands the browser a signature only Razorpay and we can make: HMAC of "order_id|payment_id" with the key secret. */
    public function confirm(Payment $payment, array $input): void
    {
        $paymentId = $input['razorpay_payment_id'] ?? null;
        $signature = $input['razorpay_signature'] ?? null;
        if (! is_string($paymentId) || ! is_string($signature) || ($input['razorpay_order_id'] ?? null) !== $payment->gateway_order_id) {
            return;
        }
        $expected = hash_hmac('sha256', $payment->gateway_order_id.'|'.$paymentId, (string) PaymentSettings::secret('razorpay', 'keySecret'));
        if (hash_equals($expected, $signature)) {
            $payment->markPaid($paymentId);
        }
    }

    public function hasWebhookSecret(): bool
    {
        return PaymentSettings::hasSecret('razorpay', 'webhookSecret');
    }

    public function validWebhook(Request $request): bool
    {
        $signature = (string) $request->header('X-Razorpay-Signature');
        $secret = PaymentSettings::secret('razorpay', 'webhookSecret');

        return filled($secret) && $signature !== ''
            && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function webhookOutcome(array $event): ?array
    {
        $payment = $event['payload']['payment']['entity'] ?? [];
        $order = $payment['order_id'] ?? $event['payload']['order']['entity']['id'] ?? null;
        if (! is_string($order)) {
            return null;
        }

        return match ($event['event'] ?? null) {
            'payment.captured', 'order.paid' => ['order' => $order, 'payment' => $payment['id'] ?? null, 'status' => 'paid', 'reason' => null],
            'payment.failed' => ['order' => $order, 'payment' => $payment['id'] ?? null, 'status' => 'failed', 'reason' => $payment['error_description'] ?? 'Payment failed'],
            default => null,
        };
    }

    public function check(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Fill in the key ID and key secret first.'];
        }

        return $this->checkResult($this->http()->get(self::API.'/orders', ['count' => 1])->status());
    }

    private function keyId(): ?string
    {
        return PaymentSettings::settings()['razorpay']['keyId'] ?? null;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson()->withBasicAuth((string) $this->keyId(), (string) PaymentSettings::secret('razorpay', 'keySecret'));
    }
}
