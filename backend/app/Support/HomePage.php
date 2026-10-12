<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;

/**
 * Homepage content (Website → Home page). Defaults come from content/home.json, which the
 * website also uses when it is built without the API.
 */
class HomePage
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return once(function () {
            $file = rtrim((string) config('instacertify.content_path'), '/\\').'/home.json';

            return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        });
    }

    /**
     * The site.json the database was first seeded from; its stats, process and whyUs lists must stay
     * unchanged so untouched copies in the database can be told apart from edited ones.
     *
     * @return array<string, mixed>
     */
    private static function originalSite(): array
    {
        return once(function () {
            $file = rtrim((string) config('instacertify.content_path'), '/\\').'/site.json';

            return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        });
    }

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        return self::merge(self::defaults(), Setting::get('home') ?? self::legacy());
    }

    /**
     * Settings with uploaded images turned into public URLs.
     *
     * @param  array<string, mixed>|null  $home  unsaved settings to show instead, e.g. for a preview
     */
    public static function forApi(?array $home = null): array
    {
        $home ??= self::settings();
        $home['hero']['image'] = Images::url($home['hero']['image'] ?? null);
        $home['hero']['pillars'] = collect($home['hero']['pillars'] ?? [])
            ->map(fn (array $slide) => [...$slide, 'image' => Images::url($slide['image'] ?? null), 'iconImage' => Images::url($slide['iconImage'] ?? null)])->values()->all();
        $home['clientLogos'] = collect($home['clientLogos'] ?? [])
            ->map(fn (array $logo) => [...$logo, 'image' => Images::url($logo['image'] ?? null)])->values()->all();
        $home['promos']['items'] = collect($home['promos']['items'] ?? [])
            ->map(fn (array $promo) => [...$promo, 'image' => Images::url($promo['image'] ?? null)])->values()->all();
        foreach (['stats', 'popular', 'explore', 'whatWeDo', 'process', 'testingProcess', 'whyUs', 'promos'] as $section) {
            if (isset($home[$section]['items'])) {
                $home[$section]['items'] = collect($home[$section]['items'])
                    ->map(fn (array $item) => [...$item, 'iconImage' => Images::url($item['iconImage'] ?? null)])->values()->all();
            }
        }
        $home['seo'] = [...Arr::except($home['seo'] ?? [], ['schema', 'schema_mode']), ...StructuredData::forApi($home['seo'] ?? [])];

        return $home;
    }

    /**
     * The steps shared by the home page and the service pages' "How it works".
     *
     * @param  array<string, mixed>  $home  as returned by forApi()
     * @return list<array<string, mixed>>
     */
    public static function process(array $home): array
    {
        return collect($home['process']['items'] ?? [])->map(fn (array $step) => Arr::only($step, ['title', 'text', 'icon', 'iconImage']))->values()->all();
    }

    /**
     * Saved values win, including ones cleared on purpose; nested sections merge key by key,
     * while lists (tiles, FAQs, logos) are replaced as a whole.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $saved): array
    {
        foreach ($saved as $key => $value) {
            $default = $defaults[$key] ?? null;
            $defaults[$key] = is_array($value) && is_array($default) && ! array_is_list($default) && ! array_is_list($value)
                ? self::merge($default, $value)
                : $value;
        }

        return $defaults;
    }

    /**
     * Content saved before the home page had its own editor lived in the site settings.
     *
     * @return array<string, mixed>
     */
    private static function legacy(): array
    {
        $site = Setting::get('site', []);
        $defaults = self::defaults();
        $original = self::originalSite();
        $withIcons = fn (string $section, array $items) => collect($items)
            ->map(fn (array $item, int $i) => [...$item, 'icon' => $item['icon'] ?? $defaults[$section]['items'][$i]['icon'] ?? null])
            ->values()->all();
        // Lists still identical to the shipped site.json were never edited, so the current defaults replace them.
        $edited = fn (string $key) => isset($site[$key]) && $site[$key] != ($original[$key] ?? null);

        $legacy = array_filter([
            'hero' => array_filter($site['hero'] ?? [], fn ($v) => $v !== null),
            'stats' => $edited('stats') ? ['items' => $withIcons('stats', $site['stats'])] : null,
            'process' => $edited('process') ? ['items' => $withIcons('process', $site['process'])] : null,
            'whyUs' => $edited('whyUs') ? ['items' => $withIcons('whyUs', $site['whyUs'])] : null,
        ]);
        foreach (['clientLogosEnabled', 'clientLogosTitle', 'clientLogos'] as $key) {
            if (array_key_exists($key, $site)) {
                $legacy[$key] = $site[$key];
            }
        }

        return $legacy;
    }
}
