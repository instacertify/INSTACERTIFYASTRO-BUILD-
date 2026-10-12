<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/** Searchable picker for Google Material Symbols (fonts.google.com/icons), previewed with the icon font in the admin. */
class IconPicker
{
    /** Shown before the editor types anything: icons that suit certification and compliance content. */
    public const SUGGESTED = [
        'verified', 'verified_user', 'workspace_premium', 'license', 'approval', 'task_alt', 'fact_check', 'rule',
        'policy', 'gavel', 'shield_lock', 'health_and_safety', 'science', 'biotech', 'experiment', 'lab_profile',
        'precision_manufacturing', 'factory', 'engineering', 'support_agent', 'person_check', 'groups', 'handshake',
        'request_quote', 'receipt_long', 'currency_rupee', 'description', 'assignment', 'assignment_turned_in',
        'edit_document', 'contract', 'app_registration', 'inventory_2', 'local_shipping', 'speed', 'bolt', 'timer',
        'schedule', 'calendar_month', 'event', 'history', 'pending_actions', 'rocket_launch', 'trophy', 'thumb_up',
        'public', 'language', 'travel_explore', 'flag', 'euro', 'globe_asia', 'globe_uk', 'storefront', 'eco',
        'electrical_services', 'devices', 'mobile', 'router', 'toys', 'medical_services', 'restaurant',
        'chat', 'forum', 'quiz', 'manage_search', 'search_check',
    ];

    public static function make(string $name = 'icon'): Select
    {
        return Select::make($name)
            ->label('Icon')
            ->searchable()
            ->allowHtml()
            ->options(fn () => self::labels(array_values(array_intersect(self::SUGGESTED, self::names()))))
            ->getSearchResultsUsing(fn (string $search) => self::labels(self::search($search)))
            ->getOptionLabelUsing(fn (?string $value) => filled($value) ? self::label($value) : null)
            ->rule(fn () => Rule::in(self::names()))
            ->helperText(new HtmlString('Type to search all Google icons, or find one on <a href="https://fonts.google.com/icons" target="_blank" rel="noopener" class="font-medium text-primary-600 underline">fonts.google.com/icons</a> and type its name (e.g. "verified").'));
    }

    /** @return list<string> */
    public static function names(): array
    {
        return once(fn () => json_decode((string) file_get_contents(resource_path('data/material-symbols.json')), true) ?: []);
    }

    /** @return list<string> */
    public static function search(string $search, int $limit = 60): array
    {
        $term = trim(preg_replace('/[\s_-]+/', ' ', strtolower($search)));
        if ($term === '') {
            return [];
        }
        $exact = str_replace(' ', '_', $term);

        return collect(self::names())
            ->filter(fn (string $name) => str_contains(str_replace('_', ' ', $name), $term))
            ->sortBy(fn (string $name) => [$name !== $exact, ! str_starts_with($name, $exact), strlen($name)])
            ->take($limit)->values()->all();
    }

    public static function label(string $name): string
    {
        return '<span style="display:inline-flex;align-items:center;gap:.5rem"><span class="material-symbols-rounded" style="font-size:20px;line-height:1">'.e($name).'</span>'.e(str_replace('_', ' ', $name)).'</span>';
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private static function labels(array $names): array
    {
        return collect($names)->mapWithKeys(fn (string $name) => [$name => self::label($name)])->all();
    }
}
