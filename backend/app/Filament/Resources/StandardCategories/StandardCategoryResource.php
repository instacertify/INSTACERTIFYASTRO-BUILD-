<?php

namespace App\Filament\Resources\StandardCategories;

use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Resources\StandardCategories\Pages\ManageStandardCategories;
use App\Models\StandardCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class StandardCategoryResource extends Resource
{
    protected static ?string $model = StandardCategory::class;

    protected static ?string $modelLabel = 'standard category';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Standards library';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required(),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->prefix('/resources/standards/'),
            Select::make('group')->label('Tile on the standards library page')->options(StandardCategory::GROUPS)->required()->default('mandatory'),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            ImageUpload::make('image', 'standards'),
            Toggle::make('is_published')->label('Live')->default(true),
            SeoSection::make(reviewer: false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')->description(fn (StandardCategory $record) => $record->url()),
                TextColumn::make('group')->badge()->color('gray')->formatStateUsing(fn (?string $state) => StandardCategory::GROUPS[$state] ?? $state),
                TextColumn::make('standards_count')->counts('standards')->label('Standards'),
                ToggleColumn::make('is_published')->label('Live'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStandardCategories::route('/')];
    }
}
