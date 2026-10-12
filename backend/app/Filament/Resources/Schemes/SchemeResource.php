<?php

namespace App\Filament\Resources\Schemes;

use App\Filament\Resources\Schemes\Pages\ManageSchemes;
use App\Models\Scheme;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Certification tags on audits and events; the Audits and Events pages and the landing pages filter by them. */
class SchemeResource extends Resource
{
    protected static ?string $model = Scheme::class;

    protected static ?string $modelLabel = 'certification tag';

    protected static ?string $pluralModelLabel = 'certification tags';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    protected static ?string $navigationLabel = 'Certification tags';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(60)->unique(ignoreRecord: true)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null)
                ->helperText('Shown in the filters on the Audits and Events pages, e.g. “BIS ISI mark”.'),
            TextInput::make('slug')->required()->maxLength(60)->unique(ignoreRecord: true)
                ->helperText('Used in links and by landing pages. Changing it un-picks it on landing pages that use it.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('slug')->color('gray')->toggleable(),
                TextColumn::make('audits_count')->label('Audits')->alignEnd()
                    ->counts(['posts as audits_count' => fn ($query) => $query->where('type', 'audit')])
                    ->formatStateUsing(fn (int $state) => $state ?: '—'),
                TextColumn::make('events_count')->label('Events')->alignEnd()->counts('events')
                    ->formatStateUsing(fn (int $state) => $state ?: '—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Audits and events keep their other tags; landing pages that pick only this tag stop showing audits.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSchemes::route('/')];
    }
}
