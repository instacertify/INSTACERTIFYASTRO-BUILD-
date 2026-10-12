<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Sends a new lead to the CRM webhook; retried on its own so the team email is never sent twice. */
class SyncLeadToWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public function __construct(public Lead $lead) {}

    public function handle(): void
    {
        $lead = $this->lead->loadMissing('service');
        $payload = [
            'event' => 'lead.created',
            'lead' => [
                ...$lead->makeVisible(['notes'])->toArray(),
                'service_title' => $lead->service?->title,
            ],
        ];

        Http::timeout(10)
            ->withHeaders(['X-Instacertify-Signature' => $this->sign($payload)])
            ->post(config('instacertify.leads.webhook_url'), $payload)
            ->throw();

        $lead->forceFill(['synced_at' => now()])->saveQuietly();
    }

    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), (string) config('instacertify.leads.webhook_secret'));
    }
}
