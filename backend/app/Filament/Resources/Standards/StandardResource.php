<?php

namespace App\Filament\Resources\Standards;

use App\Filament\Components\FaqRepeater;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Resources\Standards\Pages\CreateStandard;
use App\Filament\Resources\Standards\Pages\EditStandard;
use App\Filament\Resources\Standards\Pages\ListStandards;
use App\Models\IndustryCategory;
use App\Models\Standard;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class StandardResource extends Resource
{
    protected static ?string $model = Standard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Standards library';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()->columnSpan(2)->schema([
                    TextInput::make('title')->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->helperText('URL becomes /resources/standards/{category}/{slug}.'),
                    ImageUpload::inEditor(RichEditor::make('description'), 'standards/inline'),
                    TextInput::make('products_title')->label('Product table heading'),
                    Repeater::make('products')
                        ->helperText('The page status, dates and sector follow these rows. Rows imported from the standards library are refreshed by `php artisan standards:import`.')
                        ->schema([
                            TextInput::make('is_number')->label('IS number')->required(),
                            TextInput::make('name')->label('Product')->required(),
                            ImageUpload::make('image', 'standards/products', maxWidth: 800)->label('Product photo')
                                ->imagePreviewHeight('120')->columnSpanFull(),
                            Select::make('status')->options(Standard::REGULATION),
                            TextInput::make('since')->label('Status date shown')->placeholder('17 Feb 2003'),
                            DatePicker::make('mandatory_from')->label('Mandatory from')->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Upcoming QCOs switch to mandatory on this date.'),
                            TextInput::make('sector')->datalist(fn () => IndustryCategory::orderBy('name')->pluck('name')->all()),
                            TextInput::make('hsn')->label('HSN code'),
                            TextInput::make('order')->label('Quality Control Order')->columnSpanFull(),
                            Fieldset::make('BIS fee by firm size')->columns(4)->columnSpanFull()->schema([
                                self::rupees('fee_large', 'Large'),
                                self::rupees('fee_medium', 'Medium'),
                                self::rupees('fee_small', 'Small'),
                                self::rupees('fee_micro', 'Micro'),
                                TextInput::make('unit_fee')->label('Unit rate')->placeholder('₹1.75 per tonne')->columnSpanFull(),
                            ]),
                            Fieldset::make('Testing')->columns(3)->columnSpanFull()->schema([
                                self::rupees('test_min', 'From'),
                                self::rupees('test_max', 'Up to'),
                                TextInput::make('labs')->label('Labs quoting')->numeric()->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                TextInput::make('lab_names')->label('Lab names')->columnSpanFull(),
                            ]),
                            Textarea::make('note')->rows(2)->columnSpanFull(),
                        ])
                        ->itemLabel(fn (array $state) => trim(($state['is_number'] ?? '').' · '.($state['name'] ?? ''), ' ·') ?: null)
                        ->columns(2)->defaultItems(0)->collapsible()->collapsed(fn (?array $state) => count($state ?? []) > 3),
                    Repeater::make('facts')->label('Key facts')
                        ->schema([
                            TextInput::make('label')->required(),
                            TextInput::make('value')->required(),
                        ])
                        ->columns(2)->defaultItems(0)->collapsible(),
                    FaqRepeater::make(),
                ]),
                Group::make()->columnSpan(1)->schema([
                    Section::make('Product image')
                        ->description('Shown at the top of the page, next to the title.')
                        ->schema([
                            ImageUpload::make('image', 'standards/pages', maxWidth: 1600)->hiddenLabel()
                                ->imagePreviewHeight('200')
                                ->imageEditorAspectRatioOptions([null, '4:3', '1:1', '16:9'])
                                ->helperText('PNG, JPG, WebP, GIF, AVIF or SVG, up to 8 MB. Click the pencil to crop or rotate. Photos are converted to WebP and resized automatically. If empty, the first product photo below is used.'),
                            TextInput::make('image_alt')->label('Describe the image')->maxLength(160)
                                ->placeholder('e.g. Robot vacuum cleaner with ISI mark')
                                ->helperText('Read out by screen readers and used by Google Images. Leave empty to use the page title.'),
                        ]),
                    Section::make('Publishing')->schema([
                        Select::make('standard_category_id')->label('Category')->relationship('category', 'title')->required()->preload(),
                        ToggleButtons::make('status')->options(Standard::STATUSES)->inline()->default('published')->required()
                            ->colors(['draft' => 'gray', 'published' => 'success']),
                        TextInput::make('path')->disabled()->dehydrated(false)->prefix('/'),
                        Select::make('scheme')->options(Standard::SCHEMES),
                        Select::make('regulation_status')->label('Regulation')->options(Standard::REGULATION)
                            ->helperText('Set automatically from product rows that have a status.'),
                        TextInput::make('mandatory_since')->label('Status date shown')->maxLength(60),
                        DatePicker::make('mandatory_from')->label('Mandatory from'),
                        Select::make('industry_category_id')->label('Industry')
                            ->relationship('industryCategory', 'name')->searchable()->preload()
                            ->helperText('Shown as the sector on the website. Follows the product rows\' sector when those change.'),
                    ]),
                ]),
                SeoSection::make(reviewer: false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                ImageColumn::make('image')->disk(config('instacertify.media_disk'))->imageHeight(40)->label('')->toggleable(),
                TextColumn::make('title')->searchable(['title', 'slug'])->wrap()->limit(90),
                TextColumn::make('category.title')->badge()->color('gray'),
                TextColumn::make('industryCategory.name')->label('Industry')->placeholder('—')->toggleable(),
                TextColumn::make('regulation_status')->label('Regulation')->badge()
                    ->state(fn (Standard $record) => $record->currentStatus())
                    ->formatStateUsing(fn (?string $state) => Standard::REGULATION[$state] ?? $state)
                    ->color(fn (?string $state) => Standard::REGULATION_COLORS[$state] ?? 'gray')
                    ->description(fn (Standard $record) => $record->mandatory_since),
                TextColumn::make('products')->label('Products')->state(fn (Standard $record) => count($record->products ?? [])),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('source')->label('Origin')->badge()->color('gray')
                    ->state(fn (Standard $record) => $record->source === 'library' ? 'Library import' : 'Editorial')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('standard_category_id')->label('Category')->relationship('category', 'title'),
                SelectFilter::make('industry_category_id')->label('Industry')->relationship('industryCategory', 'name')->searchable()->preload(),
                SelectFilter::make('regulation_status')->label('Regulation')->options(Standard::REGULATION),
                SelectFilter::make('scheme')->options(Standard::SCHEMES),
                TernaryFilter::make('image')->label('Product image')->nullable()
                    ->trueLabel('With image')->falseLabel('Without image'),
                TernaryFilter::make('source')->label('Origin')
                    ->trueLabel('Library import')->falseLabel('Editorial')
                    ->queries(
                        true: fn (Builder $query) => $query->where('source', 'library'),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('source')->orWhere('source', '!=', 'library')),
                    ),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    private static function rupees(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->prefix('₹')
            ->dehydrateStateUsing(fn ($state) => filled($state) ? $state + 0 : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStandards::route('/'),
            'create' => CreateStandard::route('/create'),
            'edit' => EditStandard::route('/{record}/edit'),
        ];
    }
}
