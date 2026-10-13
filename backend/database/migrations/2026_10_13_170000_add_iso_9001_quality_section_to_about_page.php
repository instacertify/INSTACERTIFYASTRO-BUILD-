<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const QUALITY = [
        'quality_standard' => 'ISO 9001:2015',
        'quality_heading' => 'An ISO certified company, setting the benchmark for quality',
        'quality_text' => 'Instacertify is an ISO certified company. We have implemented a quality management system to ISO 9001:2015, the international standard for quality management, so every certification and testing project follows the same documented, checked and continually improved process.',
        'quality_points' => [
            'Documented processes for every certification project',
            'Every application checked before it is filed',
            'Client feedback tracked and acted on',
            'Regular internal audits and management reviews',
            'Continual improvement in speed and accuracy',
            'Clear, consistent service across all our offices',
        ],
    ];

    /** Only fills the section when it has not been set up in the admin. */
    public function up(): void
    {
        $page = Page::where('path', 'about')->where('template', 'about')->first();
        if (! $page || filled($page->data['quality_standard'] ?? null)) {
            return;
        }

        $page->update(['data' => [...($page->data ?? []), ...self::QUALITY]]);
    }

    public function down(): void
    {
        $page = Page::where('path', 'about')->where('template', 'about')->first();
        if (! $page || ($page->data['quality_standard'] ?? null) !== self::QUALITY['quality_standard']) {
            return;
        }

        $page->update(['data' => array_diff_key($page->data, self::QUALITY)]);
    }
};
