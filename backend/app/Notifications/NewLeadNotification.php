<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeadNotification extends Notification
{
    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;
        $needs = Lead::REQUIREMENTS[$lead->requirement] ?? null;
        $service = $lead->service?->title ?? $needs ?? 'Not specified';

        $mail = (new MailMessage)
            ->subject("New lead: {$lead->name} — {$service}")
            ->greeting('New '.(Lead::TYPES[$lead->type] ?? 'enquiry'))
            ->line("**Name:** {$lead->name}")
            ->line('**Email:** '.($lead->email ?? '—'))
            ->line('**Phone:** '.($lead->phone ?? '—'))
            ->line('**Company:** '.($lead->company ?? '—'))
            ->line('**Needs:** '.($needs ?? '—'))
            ->line('**Service:** '.($lead->service?->title ?? '—'))
            ->line('**Product:** '.($lead->product ?? '—'))
            ->line('**Timeline:** '.($lead->timeline ?? '—'))
            ->line('**Source:** '.($lead->source_page ?? '—').($lead->utm_source ? " (utm: {$lead->utm_source})" : ''));

        if ($lead->message) {
            $mail->line('**Message:**')->line($lead->message);
        }

        if ($lead->email) {
            $mail->replyTo($lead->email, $lead->name);
        }

        return $mail->action('Open in admin', url("/admin/leads/{$lead->id}/edit"));
    }
}
