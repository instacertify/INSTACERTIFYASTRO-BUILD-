<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Resources\Authors\Pages\ManageAuthors;
use App\Models\Author;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('role')->placeholder('e.g. Compliance Analyst'),
            TextInput::make('company'),
            TextInput::make('experience_years')->numeric()->suffix('years'),
            TextInput::make('email')->email(),
            Textarea::make('short_bio')->rows(2)->columnSpanFull(),
            Textarea::make('bio')->label('Full bio')->rows(4)->columnSpanFull(),
            Textarea::make('credentials')->rows(2)->helperText('Qualifications shown in the author box — helps Google trust (E-E-A-T).'),
            TagsInput::make('expertise')->placeholder('Add area'),
            Fieldset::make('Profiles')->statePath('links')->columns(3)->schema([
                TextInput::make('linkedin')->url(),
                TextInput::make('twitter')->url(),
                TextInput::make('website')->url(),
            ]),
            ImageUpload::make('photo', 'authors')->avatar(),
            Toggle::make('is_published')->label('Show author page')->default(true),
            SeoSection::make(reviewer: false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('photo')->disk(config('instacertify.media_disk'))->circular()->label(''),
            TextColumn::make('name')->searchable()->description(fn (Author $record) => $record->role),
            TextColumn::make('posts_count')->counts('posts')->label('Articles')->sortable(),
            ToggleColumn::make('is_published')->label('Live'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuthors::route('/')];
    }
}
