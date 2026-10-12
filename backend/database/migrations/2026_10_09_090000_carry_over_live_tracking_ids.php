<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** Keeps the Tag Manager container and Clarity project the current instacertify.com reports into. */
return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::where('key', 'site')->first();

        if (! $setting || filled($setting->value['integrations'] ?? null)) {
            return;
        }

        $setting->value = array_replace($setting->value ?? [], ['integrations' => ['gtm' => 'GTM-NJQ6BK77', 'clarity' => 'x9uh7uca0f']]);
        $setting->save();
    }

    public function down(): void {}
};
