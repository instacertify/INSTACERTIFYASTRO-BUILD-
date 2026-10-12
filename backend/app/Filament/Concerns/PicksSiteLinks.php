<?php

namespace App\Filament\Concerns;

use App\Support\HeaderMenu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/** Label + link fields for menu editors, with a search box that fills both from a page on the site. */
trait PicksSiteLinks
{
    /** @return list<Select|TextInput> */
    protected static function linkFields(int $labelLength = 60): array
    {
        return [
            Select::make('pick')->label('Fill from a page')
                ->placeholder('Search services, categories, industries or pages…')
                ->searchable()
                ->dehydrated(false)
                ->getSearchResultsUsing(fn (string $search) => self::searchLinks($search))
                ->getOptionLabelUsing(fn (?string $value) => $value ? self::linkTitle($value) : null)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                    if (! $state) {
                        return;
                    }
                    $set('href', $state);
                    if (blank($get('label'))) {
                        $set('label', self::linkTitle($state));
                    }
                    $set('pick', null);
                })
                ->columnSpanFull(),
            TextInput::make('label')->required()->maxLength($labelLength),
            TextInput::make('href')->label('Link')->required()->maxLength(255)->placeholder('/certification/india')
                ->rule('regex:/^(\/|https?:\/\/)/')
                ->validationMessages(['regex' => 'Start with / for a page on this site, or https:// for another site.']),
        ];
    }

    /** @return array<string, string> */
    private static function searchLinks(string $search): array
    {
        $term = Str::lower(trim($search));

        return collect(once(fn () => HeaderMenu::linkOptions()))
            ->flatMap(fn (array $links, string $group) => collect($links)->map(fn (string $title, string $url) => "{$group} · {$title} — {$url}"))
            ->filter(fn (string $label) => str_contains(Str::lower($label), $term))
            ->take(50)
            ->all();
    }

    private static function linkTitle(string $url): string
    {
        foreach (HeaderMenu::linkOptions() as $links) {
            if (isset($links[$url])) {
                return $links[$url];
            }
        }

        return Str::headline(basename($url));
    }
}
