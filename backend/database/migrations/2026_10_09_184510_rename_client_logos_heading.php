<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Swaps the client-logo strip heading where it still has the old default wording. */
    public function up(): void
    {
        foreach (['home', 'site'] as $key) {
            $setting = Setting::where('key', $key)->first();
            if (($setting?->value['clientLogosTitle'] ?? null) !== 'Trusted by manufacturers, brands and importers worldwide') {
                continue;
            }
            $setting->value = [...$setting->value, 'clientLogosTitle' => 'Trusted by Leading Brands Across the Globe'];
            $setting->save();
        }
    }

    public function down(): void
    {
        //
    }
};
