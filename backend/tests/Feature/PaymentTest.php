<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePayments;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Support\PaymentSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $gateway = 'razorpay'): void
    {
        Setting::updateOrCreate(['key' => 'payments'], ['value' => [
            'enabled' => true,
            'gateway' => $gateway,
            'currency' => 'INR',
            'minAmount' => 100,
            'maxAmount' => 500000,
            'razorpay' => [
                'keyId' => 'rzp_test_AbC123',
                'keySecret' => Crypt::encryptString('rzp-key-secret'),
                'webhookSecret' => Crypt::encryptString('rzp-hook-secret'),
            ],
            'stripe' => [
                'secretKey' => Crypt::encryptString('sk_test_51abc'),
                'webhookSecret' => Crypt::encryptString('whsec_stripe-hook'),
            ],
        ]]);
    }

    private function payer(array $overrides = []): array
    {
        return [
            'name' => 'Asha Verma', 'email' => 'Asha@Example.com', 'phone' => '+91 98765 43210',
            'company' => 'Volt Devices', 'invoice_number' => 'ic/2026/0142', 'amount' => '1,500.50', ...$overrides,
        ];
    }

    private function razorpayPayment(): Payment
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_Q1w2E3r4', 'status' => 'created'])]);
        $this->postJson('/api/v1/payments', $this->payer())->assertCreated();

        return Payment::sole();
    }

    public function test_admin_saves_keys_encrypted_and_the_site_only_learns_that_payments_are_on(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $site = Setting::find('site');
        $site->value = [...$site->value, 'footer' => [...($site->value['footer'] ?? []), 'bottomLinks' => [['label' => 'Pay online', 'href' => '/pay']]]];
        $site->save();
        $this->getJson('/api/v1/site')->assertJsonPath('data.payments.enabled', false)
            ->assertJsonPath('data.footer.bottomLinks', []);

        Livewire::test(ManagePayments::class)
            ->set('data.enabled', true)
            ->set('data.gateway', 'razorpay')
            ->set('data.razorpay.keyId', 'rzp_live_AbC123')
            ->set('data.razorpay.keySecret', 'rzp-key-secret')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.razorpay.keySecret', null);

        $stored = Setting::find('payments')->value;
        $this->assertSame('rzp-key-secret', Crypt::decryptString($stored['razorpay']['keySecret']));

        Livewire::test(ManagePayments::class)->set('data.heading', 'Pay an invoice')->call('save')->assertHasNoErrors();
        $this->assertSame('rzp-key-secret', PaymentSettings::secret('razorpay', 'keySecret'), 'Leaving the secret empty keeps the saved one');

        $response = $this->getJson('/api/v1/site')
            ->assertJsonPath('data.payments.enabled', true)
            ->assertJsonPath('data.payments.gateway', 'Razorpay')
            ->assertJsonPath('data.payments.testMode', false)
            ->assertJsonPath('data.payments.heading', 'Pay an invoice');
        $this->assertSame([['label' => 'Pay online', 'href' => '/pay']], $response->json('data.footer.bottomLinks'));
        $this->assertStringNotContainsString('rzp-key-secret', $response->getContent());
        $this->assertStringNotContainsString($stored['razorpay']['keySecret'], $response->getContent());
    }

    public function test_switching_on_requires_the_selected_gateways_keys(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManagePayments::class)
            ->set('data.enabled', true)
            ->set('data.gateway', 'stripe')
            ->call('save')
            ->assertHasErrors(['data.stripe.secretKey']);
    }

    public function test_payments_are_refused_while_switched_off(): void
    {
        $this->postJson('/api/v1/payments', $this->payer())->assertStatus(503);
        $this->assertSame(0, Payment::count());
    }

    public function test_amount_must_be_within_the_limits(): void
    {
        $this->configure();

        $this->postJson('/api/v1/payments', $this->payer(['amount' => '9,00,000']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/v1/payments', $this->payer(['amount' => '50']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/v1/payments', $this->payer(['invoice_number' => '']))->assertUnprocessable()->assertJsonValidationErrors('invoice_number');
        $this->postJson('/api/v1/payments', $this->payer(['website' => 'spam']))->assertUnprocessable();
    }

    public function test_razorpay_order_is_opened_for_the_amount_in_paise(): void
    {
        $this->configure();
        $payment = $this->razorpayPayment();

        $this->assertSame(150050, $payment->amount);
        $this->assertSame('IC/2026/0142', $payment->invoice_number);
        $this->assertSame('asha@example.com', $payment->email);
        $this->assertSame('order_Q1w2E3r4', $payment->gateway_order_id);
        $this->assertMatchesRegularExpression('/^IC[A-Z0-9]{14}$/', $payment->reference);
        Http::assertSent(fn (Request $request) => $request['amount'] === 150050 && $request['currency'] === 'INR'
            && $request['receipt'] === $payment->reference
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_AbC123:rzp-key-secret')));
    }

    public function test_razorpay_payment_is_marked_paid_only_with_a_valid_signature(): void
    {
        Notification::fake();
        $this->configure();
        $payment = $this->razorpayPayment();
        $confirm = fn (string $signature) => $this->postJson("/api/v1/payments/{$payment->reference}/confirm", [
            'razorpay_order_id' => 'order_Q1w2E3r4', 'razorpay_payment_id' => 'pay_Z9y8X7', 'razorpay_signature' => $signature,
        ])->assertOk();

        $confirm('forged')->assertJsonPath('data.status', 'created');
        $confirm(hash_hmac('sha256', 'order_Q1w2E3r4|pay_Z9y8X7', 'rzp-key-secret'))
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amountFormatted', '₹1,500.50');

        $this->assertSame('pay_Z9y8X7', $payment->fresh()->gateway_payment_id);
        Notification::assertSentOnDemandTimes(PaymentReceived::class, 1);
    }

    public function test_razorpay_webhook_records_the_payment_once(): void
    {
        Notification::fake();
        $this->configure();
        $payment = $this->razorpayPayment();
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_Z9y8X7', 'order_id' => 'order_Q1w2E3r4']]]]);
        $send = fn (string $signature) => $this->call('POST', '/api/v1/payments/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body);

        $send('forged')->assertStatus(400);
        $this->assertSame('created', $payment->fresh()->status);

        $signature = hash_hmac('sha256', $body, 'rzp-hook-secret');
        $send($signature)->assertOk();
        $send($signature)->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        Notification::assertSentOnDemandTimes(PaymentReceived::class, 1);
    }

    public function test_stripe_checkout_session_is_confirmed_with_stripe(): void
    {
        Notification::fake();
        $this->configure('stripe');
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_a1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_a1']),
            'api.stripe.com/v1/checkout/sessions/cs_test_a1' => Http::sequence()
                ->push(['id' => 'cs_test_a1', 'payment_status' => 'unpaid'])
                ->push(['id' => 'cs_test_a1', 'payment_status' => 'paid', 'payment_intent' => 'pi_3abc']),
        ]);

        $this->postJson('/api/v1/payments', $this->payer())
            ->assertCreated()
            ->assertJsonPath('data.checkout', ['type' => 'stripe', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_a1']);
        $payment = Payment::sole();
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request->isForm()
            && $request['line_items'][0]['price_data']['unit_amount'] === 150050
            && $request['line_items'][0]['price_data']['currency'] === 'inr'
            && str_ends_with($request['success_url'], "/pay?ref={$payment->reference}")
            && $request->hasHeader('Authorization', 'Bearer sk_test_51abc'));

        $this->postJson("/api/v1/payments/{$payment->reference}/confirm")->assertJsonPath('data.status', 'created');
        $this->postJson("/api/v1/payments/{$payment->reference}/confirm")->assertJsonPath('data.status', 'paid');
        $this->assertSame('pi_3abc', $payment->fresh()->gateway_payment_id);
    }

    public function test_stripe_webhook_checks_the_signature_and_its_age(): void
    {
        Notification::fake();
        $this->configure('stripe');
        $payment = Payment::create(['gateway' => 'stripe', 'amount' => 50000, 'currency' => 'INR', 'name' => 'Asha', 'email' => 'a@example.com', 'gateway_order_id' => 'cs_test_a1']);
        $body = json_encode(['type' => 'checkout.session.expired', 'data' => ['object' => ['object' => 'checkout.session', 'id' => 'cs_test_a1', 'payment_status' => 'unpaid']]]);
        $send = fn (int $time, string $secret) => $this->call('POST', '/api/v1/payments/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$body}", $secret),
        ], $body);

        $send(time(), 'whsec_wrong')->assertStatus(400);
        $send(time() - 600, 'whsec_stripe-hook')->assertStatus(400);
        $send(time(), 'whsec_stripe-hook')->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('Checkout closed without paying', $payment->fresh()->failure_reason);
        Notification::assertNothingSent();
    }

    public function test_a_failure_report_never_undoes_a_payment(): void
    {
        $payment = Payment::create(['gateway' => 'razorpay', 'amount' => 50000, 'currency' => 'INR', 'name' => 'Asha', 'email' => 'a@example.com']);
        $payment->markPaid('pay_1');
        $payment->markFailed('Card declined');

        $this->assertSame('paid', $payment->fresh()->status);
    }
}
