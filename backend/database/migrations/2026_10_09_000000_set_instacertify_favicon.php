<?php

use App\Models\Setting;
use App\Support\Images;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::where('key', 'site')->first();
        $source = resource_path('branding/favicon.png');

        if (! $setting || filled($setting->value['favicon'] ?? null) || ! is_file($source)) {
            return;
        }

        $path = Images::storeFavicon((string) file_get_contents($source));
        if ($path) {
            $setting->value = array_replace($setting->value ?? [], ['favicon' => $path]);
            $setting->save();
        }
    }

    public function down(): void {}
};
