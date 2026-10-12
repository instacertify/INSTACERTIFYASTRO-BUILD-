<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
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
                if (! preg_match('#^/industries/[^/\#]+$#', $href) || ! empty($item['children'])) {
                    continue;
                }
                $item['children'] = [
                    ['label' => 'Certification', 'href' => "{$href}#certification"],
                    ['label' => 'Testing', 'href' => "{$href}#testing"],
                ];
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
