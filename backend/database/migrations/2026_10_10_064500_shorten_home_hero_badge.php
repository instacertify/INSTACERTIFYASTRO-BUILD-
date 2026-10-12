<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Shortens the line above the home hero headline where it still has the old default wording. */
    public function up(): void
    {
        foreach (['home', 'site'] as $key) {
            $setting = Setting::where('key', $key)->first();
            if (($setting?->value['hero']['badge'] ?? null) !== 'Making certification simple for BIS, WPC, TEC & CE') {
                continue;
            }
            $value = $setting->value;
            $value['hero']['badge'] = 'Making Certification Simple';
            $setting->value = $value;
            $setting->save();
        }
    }

    public function down(): void
    {
        //
    }
};
