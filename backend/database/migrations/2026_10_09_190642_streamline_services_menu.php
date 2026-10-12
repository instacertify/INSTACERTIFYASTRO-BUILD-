<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Group descriptions under Services repeat the links listed below them. Edited text is left alone. */
    private const DESCRIPTIONS = [
        '/services/testing' => 'Accredited labs, 20 industries',
        '/services/indian-registration-compliances' => 'EPR, IEC, FSSAI, LMPC',
        '/services/qualitiy' => 'Inspection & market surveillance',
    ];

    private const REGISTRATIONS = '/services/indian-registration-compliances';

    private const EPR = '/services/indian-registration-compliances/environment-compliance';

    private const QUALITY = '/services/qualitiy';

    /**
     * EPR has its own column, so it leaves the registrations list; Quality assurance moves up
     * beside the registrations so the four columns come out even.
     */
    public function up(): void
    {
        $this->apply(function (array $groups) {
            $groups = array_map(function (array $group) {
                if (($group['description'] ?? null) === (self::DESCRIPTIONS[$group['href'] ?? ''] ?? false)) {
                    unset($group['description']);
                }

                return $group;
            }, $groups);

            $hrefs = array_column($groups, 'href');
            if (in_array(self::EPR, $hrefs, true)) {
                $groups = array_map(fn (array $group) => ($group['href'] ?? null) === self::REGISTRATIONS && is_array($group['children'] ?? null)
                    ? [...$group, 'children' => array_values(array_filter($group['children'], fn (array $link) => ($link['href'] ?? null) !== self::EPR))]
                    : $group, $groups);
            }

            return $this->swap($groups, self::EPR, self::QUALITY);
        });
    }

    public function down(): void
    {
        $this->apply(function (array $groups) {
            $groups = array_map(function (array $group) {
                $href = $group['href'] ?? '';
                if (isset(self::DESCRIPTIONS[$href]) && ! isset($group['description'])) {
                    $group['description'] = self::DESCRIPTIONS[$href];
                }
                if ($href === self::REGISTRATIONS && is_array($group['children'] ?? null) && ! in_array(self::EPR, array_column($group['children'], 'href'), true)) {
                    array_unshift($group['children'], ['label' => 'EPR & environment', 'href' => self::EPR]);
                }

                return $group;
            }, $groups);

            return $this->swap($groups, self::QUALITY, self::EPR);
        });
    }

    /**
     * Swaps two groups when $first comes right before $second.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function swap(array $groups, string $first, string $second): array
    {
        $hrefs = array_column($groups, 'href');
        $a = array_search($first, $hrefs, true);
        $b = array_search($second, $hrefs, true);
        if ($a !== false && $b === $a + 1) {
            [$groups[$a], $groups[$b]] = [$groups[$b], $groups[$a]];
        }

        return $groups;
    }

    private function apply(Closure $change): void
    {
        $setting = Setting::where('key', 'site')->first();
        $nav = $setting?->value['nav'] ?? null;
        if (! is_array($nav)) {
            return;
        }

        foreach ($nav as &$top) {
            if (($top['href'] ?? null) === '/services' && is_array($top['children'] ?? null)) {
                $top['children'] = $change($top['children']);
            }
        }
        unset($top);

        $setting->value = [...$setting->value, 'nav' => $nav];
        $setting->save();
    }
};
