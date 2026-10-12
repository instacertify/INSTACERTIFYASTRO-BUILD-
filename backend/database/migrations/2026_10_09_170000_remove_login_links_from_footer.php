<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** The footer no longer links to the ERP and the admin panel; other login links added in the admin stay. */
return new class extends Migration
{
    private const REMOVED = ['https://erp.instacertify.com', '/admin'];

    public function up(): void
    {
        $setting = Setting::find('site');
        $portals = $setting?->value['footer']['portals'] ?? null;
        if (! $portals) {
            return;
        }

        $value = $setting->value;
        $value['footer']['portals'] = array_values(array_filter($portals, fn (array $link) => ! in_array(rtrim($link['href'] ?? '', '/'), self::REMOVED, true)));
        $setting->update(['value' => $value]);
    }

    public function down(): void
    {
        // Links can be added again under Website → Footer.
    }
};
