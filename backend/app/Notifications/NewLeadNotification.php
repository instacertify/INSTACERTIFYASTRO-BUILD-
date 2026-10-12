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
            ->line('**Timeline:** '.($lead->timeline ?? '—'));

        if ($lead->message) {
            $mail->line('**Message:**')->line($lead->message);
        }

        $mail->line('**Submitted on page:** '.($this->pageUrl() ?? '—'))
            ->line('**Came from:** '.($lead->referrer ?: 'Not known (typed the address, or declined analytics cookies)'));
        if ($campaign = $this->campaign()) {
            $mail->line("**Campaign:** {$campaign}");
        }
        $mail->line('**IP address:** '.($lead->ip ?? '—'))
            ->line('**Submitted at:** '.$lead->created_at?->timezone('Asia/Kolkata')->format('j M Y, g:i a').' IST');

        if ($lead->email) {
            $mail->replyTo($lead->email, $lead->name);
        }

        return $mail->action('Open in admin', url("/admin/leads/{$lead->id}/edit"));
    }

    private function pageUrl(): ?string
    {
        $path = $this->lead->source_page;

        return $path && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? rtrim(config('instacertify.frontend_url'), '/').$path
            : $path;
    }

    private function campaign(): ?string
    {
        $lead = $this->lead;
        $parts = array_filter([
            $lead->utm_source ? "source {$lead->utm_source}" : null,
            $lead->utm_medium ? "medium {$lead->utm_medium}" : null,
            $lead->utm_campaign ? "campaign {$lead->utm_campaign}" : null,
            $lead->utm_term ? "keyword {$lead->utm_term}" : null,
            $lead->gclid ? 'Google Ads click' : null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
