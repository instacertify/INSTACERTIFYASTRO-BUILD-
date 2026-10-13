<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;

abstract class PaymentGateway
{
    /** Has the keys it needs to take a payment. */
    abstract public function configured(): bool;

    abstract public function testMode(): bool;

    /**
     * Opens the payment at the gateway and stores its order or session ID on the payment.
     *
     * @return array<string, mixed> what the payer's browser needs to show the checkout
     */
    abstract public function checkout(Payment $payment): array;

    /**
     * Asks the gateway whether a payment the browser reports as complete really went through, and marks it paid if so.
     *
     * @param  array<string, mixed>  $input  what the browser sent back from the checkout
     */
    abstract public function confirm(Payment $payment, array $input): void;

    abstract public function hasWebhookSecret(): bool;

    abstract public function validWebhook(Request $request): bool;

    /**
     * @param  array<string, mixed>  $event
     * @return array{order: string, payment: ?string, status: 'paid'|'failed', reason: ?string}|null null for events that change nothing
     */
    abstract public function webhookOutcome(array $event): ?array;

    /** @return array{ok: bool, message: string} */
    abstract public function check(): array;

    protected function siteName(): string
    {
        return Setting::get('site', [])['name'] ?? 'Instacertify';
    }

    protected function description(Payment $payment): string
    {
        return filled($payment->invoice_number)
            ? "{$this->siteName()} invoice {$payment->invoice_number}"
            : "Payment to {$this->siteName()}";
    }

    /** @return array{ok: bool, message: string} */
    protected function checkResult(int $status): array
    {
        return match (true) {
            $status >= 200 && $status < 300 => ['ok' => true, 'message' => 'Keys accepted.'],
            in_array($status, [401, 403], true) => ['ok' => false, 'message' => "The keys were refused ({$status}). Copy them again from the dashboard."],
            default => ['ok' => false, 'message' => "The gateway answered {$status}; try again later."],
        };
    }
}
