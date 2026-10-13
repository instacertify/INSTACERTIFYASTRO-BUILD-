<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\PaymentSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;

        return (new MailMessage)
            ->subject("Payment received: {$payment->formattedAmount()} from {$payment->name}")
            ->greeting('Payment received on the website')
            ->line("**Amount:** {$payment->formattedAmount()}")
            ->line('**Invoice:** '.($payment->invoice_number ?? '—'))
            ->line("**Name:** {$payment->name}")
            ->line("**Email:** {$payment->email}")
            ->line('**Phone:** '.($payment->phone ?? '—'))
            ->line('**Company:** '.($payment->company ?? '—'))
            ->line('**Note:** '.($payment->note ?? '—'))
            ->line("**Reference:** {$payment->reference}")
            ->line('**Gateway:** '.(PaymentSettings::GATEWAYS[$payment->gateway] ?? $payment->gateway).' — '.($payment->gateway_payment_id ?? '—'))
            ->line('**Paid at:** '.$payment->paid_at?->timezone('Asia/Kolkata')->format('j M Y, g:i a').' IST')
            ->replyTo($payment->email, $payment->name)
            ->action('Open in admin', url("/admin/payments/{$payment->id}"));
    }
}
