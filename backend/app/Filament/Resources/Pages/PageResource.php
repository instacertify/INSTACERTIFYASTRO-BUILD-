<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Components\ClientLogosToggle;
use App\Filament\Components\IconAlign;
use App\Filament\Components\IconPicker;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()->columnSpan(2)->schema([
                    TextInput::make('title')->required(),
                    TextInput::make('path')->required()->unique(ignoreRecord: true)->prefix('instacertify.com/'),
                    Textarea::make('excerpt')->rows(2)
                        ->helperText(fn (Get $get) => $get('template') === 'about' ? 'The introduction under the page title.' : null),
                    ImageUpload::inEditor(RichEditor::make('content'), 'pages/inline')
                        ->label(fn (Get $get) => $get('template') === 'about' ? 'Our story' : 'Content'),
                ]),
                Section::make('Publishing')->columnSpan(1)->schema([
                    Select::make('template')->options(Page::TEMPLATES)->required()->default('default')->live(),
                    ToggleButtons::make('status')->options(Page::STATUSES)->inline()->default('published')->required()
                        ->colors(['draft' => 'gray', 'published' => 'success']),
                    ImageUpload::make('image', 'pages'),
                    ClientLogosToggle::make(),
                ]),
                self::aboutSections(),
                Section::make('Structured blocks')
                    ->description('Imported landing/hub sections. Edit with care.')
                    ->columnSpanFull()
                    ->collapsed()
                    ->visible(fn (?Page $record, Get $get) => $get('template') !== 'about' && filled($record?->data))
                    ->schema([
                        CodeEditor::make('data')->hiddenLabel()->language(Language::Json)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null)
                            ->rule('nullable')->rule('json'),
                    ]),
                SeoSection::make(),
            ]);
    }

    /** The About fields edit "about" rather than "data" so they never share form state with the raw JSON editor. */
    public static function aboutBlocksForForm(array $data): array
    {
        if (($data['template'] ?? null) === 'about') {
            $data['about'] = $data['data'] ?? [];
        }

        return $data;
    }

    public static function aboutBlocksForSave(array $data): array
    {
        if (($data['template'] ?? null) === 'about') {
            $data['data'] = $data['about'] ?? null;
        }
        unset($data['about']);

        return $data;
    }

    private static function aboutSections(): Section
    {
        $points = fn (string $name, string $label, int $max) => Repeater::make($name)->hiddenLabel()->schema([
            TextInput::make('title')->required()->maxLength(60),
            IconPicker::make(),
            ImageUpload::icon('iconImage', 'icons/pages')->columnSpanFull(),
            Textarea::make('text')->required()->rows(2)->maxLength(240)->columnSpanFull(),
        ])->columns(2)->maxItems($max)->reorderableWithDragAndDrop()->collapsed()->addActionLabel("Add {$label}")
            ->itemLabel(fn (array $state) => $state['title'] ?? null);

        return Section::make('About page sections')
            ->description('Each section is hidden when it has no items.')
            ->columnSpanFull()
            ->visible(fn (Get $get) => $get('template') === 'about')
            ->statePath('about')
            ->schema([
                Fieldset::make('Timeline beside the story')->columns(1)->schema([
                    Repeater::make('milestones')->hiddenLabel()->schema([
                        TextInput::make('year')->required()->maxLength(12)->placeholder('2015'),
                        TextInput::make('text')->required()->maxLength(120),
                    ])->columns(['default' => 1, 'sm' => 4])->maxItems(6)->reorderableWithDragAndDrop()->addActionLabel('Add milestone')
                        ->itemLabel(fn (array $state) => $state['year'] ?? null),
                ]),
                Fieldset::make('Numbers')->columns(1)->schema([
                    TextInput::make('stats_heading')->label('Heading')->maxLength(80),
                    Repeater::make('stats')->hiddenLabel()->schema([
                        TextInput::make('value')->required()->maxLength(10)->placeholder('3000+'),
                        TextInput::make('label')->required()->maxLength(40),
                    ])->columns(2)->grid(2)->maxItems(8)->reorderableWithDragAndDrop()->addActionLabel('Add number')
                        ->itemLabel(fn (array $state) => trim(($state['value'] ?? '').' '.($state['label'] ?? '')) ?: null),
                ]),
                Fieldset::make('Mission & values')->columns(1)->schema([
                    TextInput::make('values_heading')->label('Heading')->maxLength(80),
                    Textarea::make('values_intro')->label('Introduction')->rows(2)->maxLength(240),
                    IconAlign::make('values_icon_align'),
                    $points('values', 'value', 6),
                ]),
                Fieldset::make('Why choose us')->columns(1)->schema([
                    TextInput::make('reasons_heading')->label('Heading')->maxLength(80),
                    Textarea::make('reasons_intro')->label('Introduction')->rows(2)->maxLength(240),
                    IconAlign::make('reasons_icon_align'),
                    $points('reasons', 'reason', 8),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('path')
            ->columns([
                TextColumn::make('title')->searchable(['title', 'path'])->description(fn (Page $record) => $record->url())->wrap(),
                TextColumn::make('template')->badge()->formatStateUsing(fn (string $state) => Page::TEMPLATES[$state] ?? $state),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('template')->options(Page::TEMPLATES)])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
