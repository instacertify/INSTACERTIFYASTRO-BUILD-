<?php

use App\Models\Setting;
use App\Support\Images;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Re-save client logos without their empty margins; the original files are left in place. */
    public function up(): void
    {
        foreach (['home', 'site'] as $key) {
            $setting = Setting::where('key', $key)->first();
            if (! $setting || ! is_array($setting->value['clientLogos'] ?? null)) {
                continue;
            }

            $logos = collect($setting->value['clientLogos'])->map(function (array $logo) {
                $path = $logo['image'] ?? null;
                if (blank($path) || Str::startsWith($path, ['http://', 'https://', '//']) || Str::endsWith(strtolower($path), '.svg') || ! Images::disk()->exists($path)) {
                    return $logo;
                }

                return [...$logo, 'image' => Images::storeLogo((string) Images::disk()->get($path), 'logos', basename($path))];
            })->all();

            $setting->value = [...$setting->value, 'clientLogos' => $logos];
            $setting->save();
        }
    }

    public function down(): void
    {
        //
    }
};
