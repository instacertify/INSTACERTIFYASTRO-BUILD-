<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Region descriptions under Certifications repeat the links listed below them. Edited text is left alone. */
    private const DESCRIPTIONS = [
        '/certification/india' => 'BIS, CRS, WPC, TEC, BEE, CDSCO',
        '/certification/emea' => 'CE, UKCA, SABER, SFDA',
        '/certification/asia' => 'CCC, PSE, MIC, SNI',
        '/certification/americas' => 'FCC, NOM, Anatel',
        '/certification/global-certifications' => 'ISO, CB Scheme, HACCP, Halal',
    ];

    public function up(): void
    {
        $this->apply(function (array $item) {
            if (($item['description'] ?? null) === (self::DESCRIPTIONS[$item['href'] ?? ''] ?? false)) {
                unset($item['description']);
            }

            return $item;
        });
    }

    public function down(): void
    {
        $this->apply(fn (array $item) => isset(self::DESCRIPTIONS[$item['href'] ?? '']) && ! isset($item['description'])
            ? [...$item, 'description' => self::DESCRIPTIONS[$item['href']]]
            : $item);
    }

    private function apply(Closure $change): void
    {
        $setting = Setting::where('key', 'site')->first();
        $nav = $setting?->value['nav'] ?? null;
        if (! is_array($nav)) {
            return;
        }

        foreach ($nav as &$top) {
            if (($top['href'] ?? null) === '/certification' && is_array($top['children'] ?? null)) {
                $top['children'] = array_map($change, $top['children']);
            }
        }
        unset($top);

        $setting->value = [...$setting->value, 'nav' => $nav];
        $setting->save();
    }
};
