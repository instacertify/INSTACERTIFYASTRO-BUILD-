<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 8;

    public const TYPES = [
        301 => 'Permanent (301) — keeps Google ranking',
        302 => 'Temporary (302)',
        Redirect::SAME_PAGE => 'Same page, no redirect — the old URL keeps showing the new page',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('from_path')->label('Old URL')->required()->maxLength(255)->placeholder('/old-page')
                ->helperText('The domain and any trailing slash are removed automatically.')
                ->rule(fn (?Redirect $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    $from = Redirect::normalizeFrom($value);
                    if ($from === '/') {
                        $fail('The home page cannot be redirected.');
                    } elseif (! preg_match(Redirect::PATH_PATTERN, $from)) {
                        $fail('Use only letters, numbers, hyphens, underscores, dots and slashes.');
                    } elseif (Redirect::where('from_path', $from)->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists()) {
                        $fail("{$from} already has a redirect.");
                    }
                }),
            TextInput::make('to_path')->label('New URL')->required()->maxLength(255)->placeholder('/new-page or https://…')
                ->rule(fn (Get $get, ?Redirect $record) => function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
                    $to = Redirect::normalizeTo($value);
                    if (! preg_match(Redirect::TARGET_PATTERN, $to)) {
                        $fail('Start with / for a page on this site, or https:// for another website.');

                        return;
                    }
                    if ((int) $get('status_code') === Redirect::SAME_PAGE && ! str_starts_with($to, '/')) {
                        $fail('“Same page” only works for a page on this site: start the new URL with /.');

                        return;
                    }
                    $from = Redirect::normalizeFrom($get('from_path'));
                    $others = Redirect::where('is_active', true)->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->pluck('to_path', 'from_path')->all();
                    if (Redirect::resolve($from, $to, $others) === null) {
                        $fail(Redirect::pathOf($to) === $from ? 'The new URL is the same as the old one.' : 'Other redirects send this URL back to the old one, so visitors would loop.');
                    }
                }),
            Select::make('status_code')->label('Type')->options(self::TYPES)->default(301)->required()->live(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')->label('From')->searchable(),
                TextColumn::make('to_path')->label('To')->searchable(),
                TextColumn::make('status_code')->label('Type')->badge()
                    ->formatStateUsing(fn ($state) => (int) $state === Redirect::SAME_PAGE ? 'Same page' : $state),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
