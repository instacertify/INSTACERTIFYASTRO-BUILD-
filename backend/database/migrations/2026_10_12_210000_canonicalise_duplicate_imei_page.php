<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

/** The two IMEI registration pages cover the same topic; the global page carries the October 2026 rewrite, so search engines are pointed there. */
return new class extends Migration
{
    private const DUPLICATE = 'certification/india/imei-icdr-registration';

    private const CANONICAL = 'https://instacertify.com/certification/global-certifications/imei-registration';

    public function up(): void
    {
        $service = Service::firstWhere('path', self::DUPLICATE);
        if ($service && blank($service->seo['canonical'] ?? null)) {
            $service->seo = [...$service->seo ?? [], 'canonical' => self::CANONICAL];
            $service->save();
        }
    }

    public function down(): void
    {
        $service = Service::firstWhere('path', self::DUPLICATE);
        if ($service && ($service->seo['canonical'] ?? null) === self::CANONICAL) {
            $seo = $service->seo;
            unset($seo['canonical']);
            $service->seo = $seo ?: null;
            $service->save();
        }
    }
};
