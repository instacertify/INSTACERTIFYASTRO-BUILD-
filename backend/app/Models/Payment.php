<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Notifications\PaymentReceived;
use App\Support\MailSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/** A payment made on the website's /pay page. Amounts are stored in the currency's smallest unit (paise, cents). */
#[Fillable([
    'gateway', 'status', 'amount', 'currency', 'name', 'email', 'phone', 'company', 'invoice_number', 'note',
    'gateway_order_id', 'gateway_payment_id', 'failure_reason', 'paid_at',
])]
class Payment extends Model
{
    use Audited;

    public const STATUSES = [
        'created' => 'Awaiting payment',
        'paid' => 'Paid',
        'failed' => 'Failed',
    ];

    public const STATUS_COLORS = [
        'created' => 'gray',
        'paid' => 'success',
        'failed' => 'danger',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Shown to the payer and used in the status link, so it must not be guessable.
        static::creating(function (Payment $payment) {
            $payment->reference ??= 'IC'.strtoupper(Str::random(14));
        });
    }

    public function formattedAmount(): string
    {
        return Number::currency($this->amount / 100, in: $this->currency, locale: 'en_IN');
    }

    /**
     * The browser and the gateway's webhook both report a payment, often at the same moment;
     * only the first one marks it paid and emails the team.
     */
    public function markPaid(?string $gatewayPaymentId): bool
    {
        $updated = static::whereKey($this->getKey())->where('status', '!=', 'paid')->update([
            'status' => 'paid',
            'gateway_payment_id' => $gatewayPaymentId ?? $this->gateway_payment_id,
            'failure_reason' => null,
            'paid_at' => now(),
            'updated_at' => now(),
        ]);
        $this->refresh();

        if ($updated && $recipients = MailSettings::leadRecipients()) {
            rescue(fn () => Notification::route('mail', $recipients)->notify(new PaymentReceived($this)));
        }

        return $updated > 0;
    }

    /** A failed attempt never overrides a payment that went through. */
    public function markFailed(?string $reason): void
    {
        static::whereKey($this->getKey())->where('status', 'created')->update([
            'status' => 'failed',
            'failure_reason' => Str::limit((string) $reason, 250) ?: null,
            'updated_at' => now(),
        ]);
        $this->refresh();
    }

    /** @return array<string, mixed> what the payer's browser may see about the payment */
    public function forPayer(): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'amountFormatted' => $this->formattedAmount(),
            'invoiceNumber' => $this->invoice_number,
            'paidAt' => $this->paid_at?->toIso8601String(),
        ];
    }
}
