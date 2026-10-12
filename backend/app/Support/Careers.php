<?php

namespace App\Support;

use App\Models\Setting;

/** Careers page settings (Site settings → Careers) with sensible defaults. */
class Careers
{
    public const DEFAULTS = [
        'headline' => 'Build a career in product compliance',
        'intro' => 'Join the team that helps manufacturers and brands get their products certified for India and 40+ markets. Real responsibility, regulators and labs on speed dial, and a steep learning curve.',
        'perks' => [
            ['title' => 'Learn fast', 'text' => 'Work across BIS, WPC, TEC, CE and global schemes with experienced compliance specialists.'],
            ['title' => 'Real ownership', 'text' => 'Handle client projects end to end — from test plans to the final certificate.'],
            ['title' => 'Grow with us', 'text' => 'Clear growth paths, regular training and support for professional certifications.'],
        ],
        'generalApplications' => true,
    ];

    /** @return array{headline: string, intro: string, perks: list<array{title: string, text: string}>, generalApplications: bool} */
    public static function settings(): array
    {
        $saved = array_filter(Setting::get('site', [])['careers'] ?? [], fn ($v) => $v !== null && $v !== '' && $v !== []);

        return array_replace(self::DEFAULTS, $saved);
    }
}
