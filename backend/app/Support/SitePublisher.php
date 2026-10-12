<?php

namespace App\Support;

use App\Jobs\PublishWebsite;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Talks to the site builder that rebuilds the static website from the admin's content (frontend/scripts/publish-server.mjs). */
class SitePublisher
{
    public static function configured(): bool
    {
        return filled(config('instacertify.frontend_rebuild_hook')) && filled(config('instacertify.site_publish_token'));
    }

    /** Publishes shortly after the latest content change; changes saved together go live in one rebuild. */
    public static function queue(): void
    {
        if (self::configured()) {
            rescue(fn () => PublishWebsite::dispatch()->delay(now()->addSeconds(config('instacertify.publish_delay'))), report: false);
        }
    }

    /** Asks the builder to rebuild now; while it is building, it builds once more afterwards. */
    public static function request(): bool
    {
        return self::configured() && self::client()->post('/publish')->successful();
    }

    /**
     * The builder's state: status (idle, building or failed), publishedAt, pages and error.
     *
     * @return array<string, mixed>|null null when the builder cannot be reached
     */
    public static function status(): ?array
    {
        if (! self::configured()) {
            return null;
        }

        try {
            $response = self::client()->get('/status');

            return $response->successful() ? $response->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function client()
    {
        return Http::baseUrl(rtrim(config('instacertify.frontend_rebuild_hook'), '/'))
            ->withToken(config('instacertify.site_publish_token'))
            ->acceptJson()
            ->timeout(5);
    }
}
