<?php

namespace App\Support;

use App\Models\Setting;

/** Privacy settings (Site settings → Privacy & cookies) with safe defaults. */
class Privacy
{
    public const DEFAULTS = [
        'version' => '2026-10',
        'grievanceOfficer' => 'Grievance Officer',
        'grievanceEmail' => 'contact@instacertify.com',
        'consentLogMonths' => 24,
        'leadMonths' => 60,
        'applicationMonths' => 12,
        'ipDays' => 180,
    ];

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        return array_replace(self::DEFAULTS, array_filter(Setting::get('site', [])['privacy'] ?? [], fn ($v) => filled($v)));
    }

    public static function version(): string
    {
        return (string) self::settings()['version'];
    }

    public static function hashIp(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}
