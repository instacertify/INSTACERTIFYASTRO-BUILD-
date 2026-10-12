<?php

namespace App\Filament\Resources\Events;

use App\Filament\Components\DiscoverFields;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\IndustryCategory;
use App\Models\Scheme;
use App\Support\Discover;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    protected static ?string $navigationLabel = 'Trade fairs & events';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()->columnSpan(2)->columns(2)->schema([
                    TextInput::make('title')->required()->columnSpanFull()
                        ->live(onBlur: true)
                        ->helperText(fn (?string $state) => Discover::headlineHint($state))
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                            if (blank($get('schemes'))) {
                                $industry = IndustryCategory::find($get('industry_category_id') ?: IndustryCategory::suggest($state, $get('excerpt')))?->name;
                                $set('schemes', array_map('strval', Scheme::suggest($state, $get('excerpt'), $industry)));
                            }
                        }),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->prefix('instacertify.com/resources/events/')->columnSpanFull(),
                    Select::make('type')->options(Event::TYPES)->required()->default('conference'),
                    TextInput::make('location')->placeholder('City, Country or "Online"'),
                    DateTimePicker::make('starts_at')->label('Starts')->required()->seconds(false),
                    DateTimePicker::make('ends_at')->label('Ends')->seconds(false)->afterOrEqual('starts_at'),
                    TextInput::make('url')->label('Registration / event website')->url(),
                    TextInput::make('price')->placeholder('Free'),
                    DiscoverFields::summary()->columnSpanFull(),
                    ImageUpload::inEditor(RichEditor::make('content'), 'events/inline')->columnSpanFull(),
                ]),
                Group::make()->columnSpan(1)->schema([
                    Section::make('Publishing')->schema([
                        ToggleButtons::make('status')->options(Event::STATUSES)->inline()->default('draft')->required()
                            ->colors(['draft' => 'gray', 'published' => 'success']),
                        Select::make('industry_category_id')->label('Industry')
                            ->relationship('industryCategory', 'name')->searchable()->preload()
                            ->helperText('Leave empty on a new event and it is picked from the title.'),
                        Select::make('schemes')->label('Certifications exhibitors need')->multiple()->preload()
                            ->relationship('schemes', 'name')
                            ->helperText('Suggested from the title and industry. Visitors can filter the Events page by these.'),
                        DiscoverFields::featuredImage('events'),
                        DiscoverFields::imageAlt(),
                    ]),
                    DiscoverFields::checklist(),
                ]),
                SeoSection::make(reviewer: false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap()->limit(70)->description(fn (Event $record) => $record->location),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => Event::TYPES[$state] ?? $state),
                TextColumn::make('industryCategory.name')->label('Industry')->placeholder('—')->toggleable(),
                TextColumn::make('schemes.name')->label('Certifications')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('starts_at')->label('Date')->date()->sortable()
                    ->color(fn (Event $record) => $record->starts_at->isPast() ? 'gray' : 'success'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                DiscoverFields::column(),
            ])
            ->filters([
                Filter::make('upcoming')->query(fn (Builder $query) => $query->upcoming())->default(),
                SelectFilter::make('type')->options(Event::TYPES),
                SelectFilter::make('industry_category_id')->label('Industry')->relationship('industryCategory', 'name')->searchable()->preload(),
                SelectFilter::make('schemes')->label('Certification')->relationship('schemes', 'name')->preload(),
                DiscoverFields::filter(),
            ])
            ->recordActions([
                ReplicateAction::make()->iconButton()
                    ->beforeReplicaSaved(fn (Event $replica) => $replica->fill(['slug' => $replica->slug.'-copy', 'status' => 'draft'])),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
