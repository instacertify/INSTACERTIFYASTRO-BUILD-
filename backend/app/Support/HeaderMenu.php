<?php

namespace App\Support;

use App\Models\Industry;
use App\Models\Page;
use App\Models\Service;
use App\Models\Setting;

/** The website header: top bar items → dropdown links → flyout links, stored as `nav` in the site settings. */
class HeaderMenu
{
    public const LEVELS = 3;

    /** @return list<array<string, mixed>> */
    public static function items(): array
    {
        return Setting::get('site', [])['nav'] ?? [];
    }

    /**
     * Visible items only, without admin-only keys.
     *
     * @param  array<int|string, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function forApi(array $items, int $level = 1): array
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null) && filled($item['href'] ?? null) && empty($item['hidden']))
            ->map(function (array $item) use ($level) {
                $children = $level < self::LEVELS ? self::forApi($item['children'] ?? [], $level + 1) : [];

                return array_filter([
                    'label' => trim($item['label']),
                    'href' => trim($item['href']),
                    'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
                    'children' => $children ?: null,
                ]);
            })
            ->values()
            ->all();
    }

    /** Grouped link choices for the admin picker: [group => [url => title]]. */
    public static function linkOptions(): array
    {
        $services = Service::where('is_published', true)->orderBy('title')->get(['path', 'title', 'kind']);
        $kind = fn (string $k) => $services->where('kind', $k)->mapWithKeys(fn (Service $s) => ['/'.$s->path => $s->title])->all();

        return array_filter([
            'Hub pages' => [
                '/certification' => 'All certifications',
                '/certification/india' => 'India certifications',
                '/certification/emea' => 'Europe & Middle East certifications',
                '/certification/asia' => 'Asia certifications',
                '/certification/americas' => 'Americas certifications',
                '/certification/global-certifications' => 'Global certifications',
                '/services' => 'All services',
                '/services/testing' => 'Product testing',
                '/services/indian-registration-compliances' => 'Indian registrations & licences',
                '/services/qualitiy' => 'Quality assurance',
                '/industries' => 'Industries',
                '/resources' => 'Resources',
                '/resources/blogs' => 'Blog',
                '/resources/news' => 'Regulatory news',
                '/resources/update' => 'Regulatory updates',
                '/resources/audits' => 'Audits & inspections',
                '/resources/events' => 'Events',
                '/resources/standards' => 'Standards library',
                '/contact' => 'Contact',
                '/careers' => 'Careers',
            ],
            'Testing categories' => collect(TestCategories::options())->mapWithKeys(fn (string $label, string $slug) => ['/services/testing/'.$slug => $label])->all(),
            'Certifications' => $kind('certification'),
            'Testing services' => $kind('testing'),
            'Registrations & licences' => $kind('registration'),
            'Quality' => $kind('quality'),
            'Industries' => Industry::orderBy('sort')->get(['slug', 'name'])->flatMap(fn (Industry $i) => [
                '/industries/'.$i->slug => $i->name,
                '/industries/'.$i->slug.'#certification' => "{$i->name}: certification",
                '/industries/'.$i->slug.'#testing' => "{$i->name}: testing",
            ])->all(),
            'Pages' => Page::published()->orderBy('title')->get(['path', 'title'])->mapWithKeys(fn (Page $p) => ['/'.$p->path => $p->title])->all(),
        ]);
    }
}
