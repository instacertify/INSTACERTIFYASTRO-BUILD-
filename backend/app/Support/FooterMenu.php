<?php

namespace App\Support;

use App\Models\Setting;

/** The website footer: link columns, newsletter box and bottom bar, stored as `footer` in the site settings. */
class FooterMenu
{
    public const PAY_PATH = '/pay';

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
        $columns = collect($footer['columns'])
            ->filter(fn ($column) => is_array($column) && filled($column['title'] ?? null) && empty($column['hidden']))
            ->map(fn (array $column) => ['title' => trim($column['title']), 'links' => self::links($column['links'] ?? [])])
            ->all();
        [$columns, $bottomLinks] = self::withPaymentLink($columns, self::links($footer['bottomLinks']));

        return [
            'columns' => collect($columns)->filter(fn (array $column) => $column['links'] !== [])->values()->all(),
            'portals' => self::links($footer['portals']),
            'bottomLinks' => $bottomLinks,
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
     * The payment page is linked only while online payments are on: added to the bottom bar,
     * unless the footer already links to it somewhere (which keeps the admin's own label and place).
     *
     * @param  list<array{title: string, links: list<array{label: string, href: string}>}>  $columns
     * @param  list<array{label: string, href: string}>  $bottomLinks
     * @return array{0: list<array<string, mixed>>, 1: list<array{label: string, href: string}>}
     */
    private static function withPaymentLink(array $columns, array $bottomLinks): array
    {
        $isPay = fn (array $link) => rtrim((string) strtok($link['href'], '?#'), '/') === self::PAY_PATH;

        if (! PaymentSettings::enabled()) {
            $without = fn (array $links) => array_values(array_filter($links, fn (array $link) => ! $isPay($link)));

            return [array_map(fn (array $column) => [...$column, 'links' => $without($column['links'])], $columns), $without($bottomLinks)];
        }
        $linked = collect($columns)->pluck('links')->flatten(1)->merge($bottomLinks)->contains($isPay);

        return [$columns, $linked ? $bottomLinks : [...$bottomLinks, ['label' => 'Make a payment', 'href' => self::PAY_PATH]]];
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
