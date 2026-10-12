<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Components\DiscoverFields;
use App\Filament\Components\FaqRepeater;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Category;
use App\Models\Post;
use App\Models\Scheme;
use App\Support\Discover;
use App\Support\SchemeClassifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $modelLabel = 'article';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    protected static ?string $recordTitleAttribute = 'title';

    /** One sidebar entry per resource section, each with its own categories underneath. */
    public static function getNavigationItems(): array
    {
        $icons = ['blog' => 'heroicon-o-pencil-square', 'news' => 'heroicon-o-newspaper', 'audit' => 'heroicon-o-clipboard-document-check', 'update' => 'heroicon-o-bell-alert'];
        $sort = 0;

        return collect(Post::SECTIONS)->map(fn (string $label, string $key) => NavigationItem::make($label)
            ->group(static::getNavigationGroup())
            ->icon($icons[$key])
            ->sort(++$sort)
            ->badge(fn () => Post::ofType($key)->count() ?: null, 'gray')
            ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.*') && static::currentSection() === $key)
            ->url(static::getUrl('index', ['section' => $key]))
            ->childItems([
                NavigationItem::make($label.' categories')->label('Categories')
                    ->isActiveWhen(fn () => request()->routeIs(CategoryResource::getRouteBaseName().'.*') && static::currentSection() === $key)
                    ->url(CategoryResource::getUrl('index', ['section' => $key])),
            ]))->values()->all();
    }

    /** The section being worked on, from the URL or the article being edited. */
    public static function currentSection(): string
    {
        $section = request()->query('section') ?? request()->query('tab');
        if (! $section && ($record = request()->route('record'))) {
            $section = ($record instanceof Post ? $record : Post::find($record))?->type;
        }

        return isset(Post::TYPES[$section ?? '']) ? $section : 'blog';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'excerpt'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText(fn (?string $state) => Discover::headlineHint($state))
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                                if ($get('type') === 'audit') {
                                    blank($get('schemes')) && $set('schemes', array_map('strval', Scheme::suggest($state, $get('excerpt'))));
                                    blank($get('work_type')) && $set('work_type', SchemeClassifier::workType($state, $get('excerpt')));
                                }
                            }),
                        TextInput::make('slug')->required()->maxLength(255)
                            ->prefix(fn (Get $get) => 'instacertify.com/'.(Post::TYPES[$get('type') ?? 'blog'][1] ?? '').'/')
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('type', $get('type'))),
                        DiscoverFields::summary(),
                        ImageUpload::inEditor(RichEditor::make('content')->required(), 'posts/inline')
                            ->extraInputAttributes(['style' => 'min-height: 24rem']),
                        FaqRepeater::make(),
                    ]),
                Group::make()->columnSpan(1)->schema([
                    Section::make('Publishing')
                        ->schema([
                            Select::make('type')->label('Section')->options(Post::SECTIONS)->required()->live()
                                ->default(fn () => static::currentSection())
                                ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                            ToggleButtons::make('status')->options(Post::STATUSES)->inline()->default('draft')->required()
                                ->colors(['draft' => 'gray', 'published' => 'success']),
                            DateTimePicker::make('published_at')->label('Publish date')->seconds(false)
                                ->helperText('Set a future date to schedule.'),
                            Select::make('category_id')->label('Category')
                                ->relationship('category', 'name', fn (Builder $query, Get $get) => $query->where('type', $get('type')))
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                    TextInput::make('slug')->required(),
                                ])
                                ->createOptionUsing(fn (array $data, Get $get) => Category::create([...$data, 'type' => $get('type')])->getKey())
                                ->preload()
                                ->helperText('Only this section\'s categories are listed.'),
                            Select::make('industry_category_id')->label('Industry')
                                ->relationship('industryCategory', 'name')->searchable()->preload()
                                ->helperText('Leave empty on a new article and it is picked from the title.'),
                            Select::make('work_type')->label('Type of work')->options(SchemeClassifier::WORK_TYPES)
                                ->visible(fn (Get $get) => $get('type') === 'audit')
                                ->helperText('Filters the Audits page and decides where it can appear on landing pages.'),
                            Select::make('schemes')->label('Certifications covered')->multiple()->preload()
                                ->relationship('schemes', 'name')
                                ->visible(fn (Get $get) => $get('type') === 'audit')
                                ->helperText('Suggested from the title. Landing pages for these certifications show this audit.'),
                            Select::make('author_id')->label('Author')->relationship('author', 'name')->preload()->searchable()->required()
                                ->helperText('Shown as a visible byline — Google expects clear authorship.'),
                            DiscoverFields::featuredImage('posts')
                                ->rule('dimensions:min_width=1200,min_height=500')
                                ->validationMessages(['dimensions' => 'The featured image must be at least 1200 px wide and 500 px tall for Google Discover (1600 × 900 px works best).']),
                            DiscoverFields::imageAlt(),
                            TextInput::make('reading_time')->numeric()->suffix('min')->disabled()->dehydrated(false)
                                ->helperText('Calculated automatically.'),
                        ]),
                    DiscoverFields::checklist(),
                ]),
                SeoSection::make(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('image')->disk(config('instacertify.media_disk'))->imageHeight(40)->label(''),
                TextColumn::make('title')->searchable(['title', 'slug'])->wrap()->limit(80)
                    ->description(fn (Post $record) => $record->category?->name),
                TextColumn::make('industryCategory.name')->label('Industry')->placeholder('—')->toggleable(),
                TextColumn::make('schemes.name')->label('Certifications')->badge()->placeholder('—')
                    ->description(fn (Post $record) => SchemeClassifier::WORK_TYPES[$record->work_type] ?? null, 'above')
                    ->visible(fn ($livewire) => ($livewire->section ?? null) === 'audit')->toggleable(),
                TextColumn::make('author.name')->placeholder('—')->toggleable(),
                TextColumn::make('status')->badge()
                    ->state(fn (Post $record) => $record->isScheduled() ? 'scheduled' : $record->status)
                    ->color(fn (string $state) => ['published' => 'success', 'scheduled' => 'info'][$state] ?? 'gray'),
                TextColumn::make('published_at')->label('Published')->date()->sortable(),
                DiscoverFields::column(),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Post::STATUSES),
                SelectFilter::make('category_id')->label('Category')
                    ->relationship('category', 'name', fn (Builder $query, $livewire) => $query->where('type', $livewire->section ?? 'blog')),
                SelectFilter::make('industry_category_id')->label('Industry')->relationship('industryCategory', 'name')->searchable()->preload(),
                SelectFilter::make('schemes')->label('Certification')->relationship('schemes', 'name')->preload()
                    ->visible(fn ($livewire) => ($livewire->section ?? null) === 'audit'),
                SelectFilter::make('work_type')->label('Type of work')->options(SchemeClassifier::WORK_TYPES)
                    ->visible(fn ($livewire) => ($livewire->section ?? null) === 'audit'),
                SelectFilter::make('author_id')->label('Author')->relationship('author', 'name'),
                DiscoverFields::filter(),
            ])
            ->recordActions([
                Action::make('view')->icon('heroicon-o-arrow-top-right-on-square')->iconButton()
                    ->url(fn (Post $record) => rtrim(config('instacertify.frontend_url'), '/').$record->url(), shouldOpenInNewTab: true),
                ReplicateAction::make()->iconButton()
                    ->beforeReplicaSaved(fn (Post $replica) => $replica->fill(['slug' => $replica->slug.'-copy', 'status' => 'draft', 'published_at' => null])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->icon('heroicon-o-check-circle')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'published'])),
                    BulkAction::make('unpublish')->label('Move to draft')->icon('heroicon-o-eye-slash')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'draft'])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
