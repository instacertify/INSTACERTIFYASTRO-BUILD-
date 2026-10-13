<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Support\ConversionApis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

/** Reports one enquiry to one platform's conversions API; one job per platform, so a failure only retries that one. */
class SendServerConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [30, 300, 1800];

    /** @param  array<string, string|bool>  $context  visitor IDs from ConversionApis::context() */
    public function __construct(public Lead $lead, public string $platform, public array $context) {}

    public function handle(): void
    {
        if (! ConversionApis::enabled($this->platform)) {
            return;
        }

        try {
            $response = ConversionApis::send($this->platform, $this->lead, $this->context);
        } catch (ConnectionException $e) {
            ConversionApis::recordResult($this->platform, 'Could not reach the platform: '.Str::limit($e->getMessage(), 200));
            throw $e;
        }

        if ($response->successful()) {
            ConversionApis::recordResult($this->platform);

            return;
        }
        $error = "{$response->status()}: ".Str::limit(trim($response->body()), 300);
        ConversionApis::recordResult($this->platform, $error);
        // A rejected payload or token fails the same way on every retry.
        if ($response->clientError() && $response->status() !== 429) {
            $this->fail(new \RuntimeException("Conversions API {$this->platform} refused the enquiry: {$error}"));

            return;
        }
        $response->throw();
    }
}
