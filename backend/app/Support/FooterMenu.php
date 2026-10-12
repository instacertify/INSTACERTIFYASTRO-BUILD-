<?php

namespace App\Support;

use App\Models\Setting;

/** The website footer: link columns, newsletter box and bottom bar, stored as `footer` in the site settings. */
class FooterMenu
{
    public const DEFAULTS = [
        'newsletter' => [
            'show' => true,
            'title' => 'Regulatory updates',
            'text' => 'New QCOs, BIS deadlines and standard revisions — one short email a month.',
        ],
        'bottomLinks' => [
            ['label' => 'Privacy', 'href' => '/privacy-policy'],
            ['label' => 'Sitemap', 'href' => '/html-sitemap'],
        ],
        'copyright' => '© {year} Instacertify. All rights reserved.',
    ];

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        return self::withDefaults(Setting::get('site', [])['footer'] ?? []);
    }

    /**
     * Visible columns and links only, without admin-only keys.
     *
     * @param  array<string, mixed>  $footer
     * @return array<string, mixed>
     */
    public static function forApi(array $footer): array
    {
        $footer = self::withDefaults($footer);
        $newsletter = $footer['newsletter'];

        return [
            'columns' => collect($footer['columns'])
                ->filter(fn ($column) => is_array($column) && filled($column['title'] ?? null) && empty($column['hidden']))
                ->map(fn (array $column) => ['title' => trim($column['title']), 'links' => self::links($column['links'] ?? [])])
                ->filter(fn (array $column) => $column['links'] !== [])
                ->values()
                ->all(),
            'portals' => self::links($footer['portals']),
            'bottomLinks' => self::links($footer['bottomLinks']),
            'newsletter' => $newsletter['show'] ? ['title' => trim($newsletter['title']), 'text' => trim($newsletter['text'])] : null,
            'copyright' => trim($footer['copyright']),
        ];
    }

    /**
     * @param  array<string, mixed>  $footer
     * @return array<string, mixed>
     */
    private static function withDefaults(array $footer): array
    {
        $newsletter = array_filter($footer['newsletter'] ?? [], fn ($value) => $value !== null && $value !== '');

        return [
            'columns' => $footer['columns'] ?? [],
            'portals' => $footer['portals'] ?? [],
            'bottomLinks' => $footer['bottomLinks'] ?? self::DEFAULTS['bottomLinks'],
            'newsletter' => [...self::DEFAULTS['newsletter'], ...$newsletter, 'show' => (bool) ($newsletter['show'] ?? true)],
            'copyright' => filled($footer['copyright'] ?? null) ? $footer['copyright'] : self::DEFAULTS['copyright'],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $links
     * @return list<array{label: string, href: string}>
     */
    private static function links(array $links): array
    {
        return collect($links)
            ->filter(fn ($link) => is_array($link) && filled($link['label'] ?? null) && filled($link['href'] ?? null) && empty($link['hidden']))
            ->map(fn (array $link) => ['label' => trim($link['label']), 'href' => trim($link['href'])])
            ->values()
            ->all();
    }
}
