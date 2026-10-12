<?php

namespace App\Jobs;

use App\Support\SitePublisher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/** Puts saved admin changes live on the website. One is pending at a time, so a burst of saves causes one rebuild. */
class PublishWebsite implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 600;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(): void
    {
        if (SitePublisher::configured() && ! SitePublisher::request()) {
            throw new RuntimeException('The site builder did not accept the publish request.');
        }
    }
}
