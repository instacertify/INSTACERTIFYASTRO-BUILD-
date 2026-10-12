<?php

namespace App\Filament\Resources\IndustryCategories;

use App\Filament\Resources\IndustryCategories\Pages\ManageIndustryCategories;
use App\Models\IndustryCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** The industry subcategories shared by every resource section; each section lists only its own items under them. */
class IndustryCategoryResource extends Resource
{
    protected static ?string $model = IndustryCategory::class;

    protected static ?string $modelLabel = 'industry subcategory';

    protected static ?string $pluralModelLabel = 'industry subcategories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    protected static ?string $navigationLabel = 'Industry subcategories';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(80)->unique(ignoreRecord: true)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null)
                ->helperText('Renaming also renames the sector shown on its standards pages.'),
            TextInput::make('slug')->required()->maxLength(80)->unique(ignoreRecord: true),
            Select::make('industry_id')->label('Industry page')->relationship('industry', 'name')->preload()
                ->helperText('Optional: the industry page on the website this subcategory belongs to.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $count = fn (string $relation, string $label, ?string $type = null) => TextColumn::make("{$relation}_".($type ?? 'all').'_count')
            ->label($label)->alignEnd()->sortable()
            ->counts([$relation.' as '.$relation.'_'.($type ?? 'all').'_count' => fn ($query) => $type ? $query->where('type', $type) : $query])
            ->formatStateUsing(fn (int $state) => $state ?: '—');

        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('industry.name')->label('Industry page')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                $count('posts', 'Blogs', 'blog'),
                $count('posts', 'News', 'news'),
                $count('posts', 'Audits', 'audit'),
                $count('posts', 'Updates', 'update'),
                $count('events', 'Events'),
                $count('standards', 'Standards'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Its articles, events and standards stay published and move to "No industry".'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIndustryCategories::route('/')];
    }
}
