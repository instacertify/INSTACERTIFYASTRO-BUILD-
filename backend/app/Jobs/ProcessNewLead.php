<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Notifications\NewLeadNotification;
use App\Support\MailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Emails a new lead to the recipients under Settings → Email & notifications. */
class ProcessNewLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public function __construct(public Lead $lead) {}

    public function handle(): void
    {
        if (! $recipients = MailSettings::leadRecipients()) {
            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(new NewLeadNotification($this->lead->loadMissing('service')));
        } catch (Throwable $e) {
            MailSettings::recordDelivery($e);

            throw $e;
        }

        MailSettings::recordDelivery();
        if (MailSettings::delivers()) {
            $this->lead->forceFill(['emailed_at' => now()])->saveQuietly();
        }
    }
}
