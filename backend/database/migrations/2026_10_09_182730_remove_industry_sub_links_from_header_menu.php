<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Drops the "Certification" and "Testing" links listed under each industry in the Industries menu. */
    public function up(): void
    {
        $setting = Setting::where('key', 'site')->first();
        $nav = $setting?->value['nav'] ?? null;
        if (! is_array($nav)) {
            return;
        }

        foreach ($nav as &$top) {
            if (($top['href'] ?? null) !== '/industries' || ! is_array($top['children'] ?? null)) {
                continue;
            }
            foreach ($top['children'] as &$item) {
                $href = (string) ($item['href'] ?? '');
                if (! is_array($item['children'] ?? null)) {
                    continue;
                }
                $item['children'] = array_values(array_filter(
                    $item['children'],
                    fn ($child) => ! in_array($child['href'] ?? null, ["{$href}#certification", "{$href}#testing"], true),
                ));
                if ($item['children'] === []) {
                    unset($item['children']);
                }
            }
            unset($item);
        }
        unset($top);

        $setting->value = [...$setting->value, 'nav' => $nav];
        $setting->save();
    }

    public function down(): void
    {
        //
    }
};
