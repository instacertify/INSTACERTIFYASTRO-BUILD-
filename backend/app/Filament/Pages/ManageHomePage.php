<?php

namespace App\Filament\Pages;

use App\Filament\Components\IconAlign;
use App\Filament\Components\IconPicker;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\RichSnippetFields;
use App\Filament\Concerns\PreviewsOnWebsite;
use App\Models\Service;
use App\Models\Setting;
use App\Support\HomePage;
use App\Support\Preview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageHomePage extends Page
{
    use PreviewsOnWebsite;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Home page';

    protected static ?string $title = 'Home page';

    protected static ?string $slug = 'home-page';

    protected static ?int $navigationSort = -10;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageContent() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Edit the text, tiles and icons on the website\'s front page. Each section can be hidden without losing its content.';
    }

    public function mount(): void
    {
        $this->form->fill(HomePage::settings());
    }

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }

    protected function previewUrl(): string
    {
        return Preview::forHome(Preview::withoutUploads($this->form->getState(shouldCallHooksBefore: false), HomePage::settings()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Hero')->icon(Heroicon::OutlinedSparkles)->statePath('hero')->schema([
                    Section::make('Headline')->columns(2)->schema([
                        TextInput::make('badge')->label('Small line above the headline (optional)')->maxLength(60)->columnSpanFull()
                            ->helperText('Part of the page’s main heading for Google, so name the services, e.g. “Making certification simple for BIS, WPC, TEC & CE”.'),
                        TextInput::make('title')->label('Headline')->required()->maxLength(90)->columnSpanFull()
                            ->helperText('Each sentence starts a new line, e.g. “Be tested. Be certified.”'),
                        TextInput::make('highlight')->label('Coloured end of the headline')->maxLength(60)->helperText('Shown on its own line in the accent colour, underlined.'),
                        Textarea::make('intro')->rows(3)->maxLength(300)->columnSpanFull(),
                        Toggle::make('showSearch')->label('Show the site search bar under the headline')->live()->columnSpanFull()
                            ->helperText('Visitors search certifications, testing, standards, products, HSN codes and guides. It takes the place of the main button and phone number here.'),
                        TextInput::make('buttonLabel')->label('Main button text')->required()->maxLength(40)
                            ->helperText(fn ($get) => $get('showSearch') ? 'Still shown under “How it works” further down the page.' : null),
                        TextInput::make('buttonLink')->label('Main button link')->required()->placeholder('/contact'),
                        Toggle::make('showPhone')->label('Show the phone number button next to it')->columnSpanFull()
                            ->visible(fn ($get) => ! $get('showSearch')),
                    ]),
                    Section::make('Beside the headline')->compact()->schema([
                        Toggle::make('showForm')->label('Show the enquiry form instead of the photo slides')
                            ->helperText('With the form off, the main button opens the enquiry pop-up.')->live(),
                        Repeater::make('pillars')->label('Photo slides')
                            ->helperText('They play in turn beside the headline, with the title and text over the bottom of the photo.')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(30)->placeholder('Be tested'),
                                IconPicker::make(),
                                ImageUpload::icon('iconImage', 'icons/home')->columnSpanFull(),
                                Textarea::make('text')->rows(2)->maxLength(110)->columnSpanFull()->helperText('Fits two lines over the photo.'),
                                ImageUpload::make('image', 'hero/slides', maxWidth: 1600)->label('Photo')->columnSpanFull()
                                    ->imageEditorAspectRatioOptions([null, '4:3'])
                                    ->helperText('Landscape, at least 1200 × 900 px, with a calm lower third. Leave empty to use the built-in photo for this icon.'),
                            ])->columns(2)->maxItems(6)->reorderableWithDragAndDrop()->collapsible()->addActionLabel('Add slide')
                            ->itemLabel(fn (array $state) => $state['title'] ?? null)
                            ->visible(fn ($get) => ! $get('showForm')),
                        Grid::make(2)->visible(fn ($get) => (bool) $get('showForm'))->schema([
                            TextInput::make('formTitle')->label('Form title')->maxLength(70)
                                ->helperText('Leave empty to use the heading from Settings → Site settings → Enquiry form.'),
                            TextInput::make('formSubtitle')->label('Form subtitle')->maxLength(120),
                        ]),
                    ]),
                    Section::make('Background image')->compact()->columns(2)->collapsible()->schema([
                        ImageUpload::make('image', 'hero', maxWidth: 2400)->label('Image')->columnSpanFull()
                            ->helperText('Landscape photo, at least 1920 × 1000 px. Leave empty for the plain background.'),
                        TextInput::make('imageAlt')->label('Describe the image')->helperText('For screen readers and Google Images. Leave empty if purely decorative.'),
                        Select::make('overlay')->label('Text style')->options([
                            'light' => 'Dark text on a light wash (bright photos)',
                            'dark' => 'White text on a dark wash (dark or busy photos)',
                        ])->default('light')->selectablePlaceholder(false),
                        Select::make('position')->label('Keep this part of the photo in view')->options([
                            'center' => 'Centre', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right',
                        ])->default('center')->selectablePlaceholder(false),
                    ]),
                ]),

                Tab::make('Numbers & logos')->icon(Heroicon::OutlinedChartBar)->schema([
                    Section::make('Numbers strip')->statePath('stats')->compact()->schema([
                        Toggle::make('visible')->label('Show this section'),
                        IconAlign::make(),
                        Repeater::make('items')->hiddenLabel()->schema([
                            TextInput::make('value')->required()->maxLength(10)->placeholder('98%'),
                            TextInput::make('label')->required()->maxLength(40),
                            IconPicker::make(),
                            ImageUpload::icon('iconImage', 'icons/home'),
                        ])->grid(2)->maxItems(4)->reorderableWithDragAndDrop()->addActionLabel('Add number')
                            ->itemLabel(fn (array $state) => trim(($state['value'] ?? '').' '.($state['label'] ?? '')) ?: null),
                    ]),
                    Section::make('Approval marks strip')->statePath('marks')->compact()->columns(2)->schema([
                        Toggle::make('visible')->label('Show this section')->columnSpanFull(),
                        TextInput::make('title')->label('Heading')->maxLength(80),
                    ]),
                    Section::make('Client logos')->compact()
                        ->description('One logo library for the home page and the certification and testing service pages.')
                        ->schema([
                            Toggle::make('clientLogosEnabled')->label('Show the scrolling logo strip below the hero'),
                            Toggle::make('clientLogosOnServices')->label('Also show it on certification and testing service pages'),
                            TextInput::make('clientLogosTitle')->label('Strip heading')->maxLength(80),
                            Repeater::make('clientLogos')->label('Logos')
                                ->helperText('Drag to reorder. All logos show at the same height, in grey, and turn colour on hover.')
                                ->schema([
                                    ImageUpload::logo('image', 'logos')->required()->imagePreviewHeight('80'),
                                    TextInput::make('name')->label('Company name')->required()->helperText('Used as alt text.'),
                                    TextInput::make('url')->label('Link (optional)')->url()->placeholder('https://…'),
                                ])
                                ->grid(3)->reorderableWithDragAndDrop()->addActionLabel('Add logo')->collapsed()
                                ->itemLabel(fn (array $state) => $state['name'] ?? null)->defaultItems(0),
                        ]),
                ]),

                Tab::make('Services & markets')->icon(Heroicon::OutlinedSquares2x2)->schema([
                    Section::make('What we do')->statePath('whatWeDo')->compact()->columns(2)
                        ->description('The row of round icons after the client logos. Four tiles fit one row.')
                        ->schema([
                            ...self::sectionHeading(),
                            Textarea::make('intro')->label('Line under the heading')->rows(2)->maxLength(200)->columnSpanFull(),
                            IconAlign::make(default: 'center'),
                            self::tiles('items', 'Tiles', withLink: true)->maxItems(4)->columnSpanFull(),
                        ]),
                    Section::make('Popular certifications')->statePath('popular')->compact()->columns(2)->schema([
                        ...self::sectionHeading(link: true),
                        IconAlign::make(),
                        Repeater::make('items')->label('Tiles')->columnSpanFull()
                            ->helperText('Leave empty to show the services marked "Featured" (up to 8), each with an icon matching its type.')
                            ->schema([
                                Select::make('path')->label('Service')->required()->searchable()
                                    ->options(fn () => Service::query()->where('is_published', true)->orderBy('title')->pluck('title', 'path')->all()),
                                IconPicker::make(),
                                ImageUpload::icon('iconImage', 'icons/home')->columnSpanFull(),
                            ])
                            ->columns(2)->maxItems(12)->reorderableWithDragAndDrop()->collapsed()->addActionLabel('Add tile')->defaultItems(0)
                            ->itemLabel(fn (array $state) => filled($state['path'] ?? null) ? Service::query()->where('path', $state['path'])->value('title') : null),
                    ]),
                    Section::make('Browse by market')->statePath('explore')->compact()->columns(2)->schema([
                        ...self::sectionHeading(),
                        self::tiles('items', 'Tiles', withLink: true)->columnSpanFull(),
                    ]),
                    Section::make('Industries')->statePath('industries')->compact()->columns(2)
                        ->description('The industry tiles and photos come from Website → Industries.')
                        ->schema(self::sectionHeading(link: true)),
                ]),

                Tab::make('How it works & why us')->icon(Heroicon::OutlinedListBullet)->schema([
                    Section::make('How it works')->statePath('process')->compact()->columns(2)
                        ->description('These steps also appear on certification and registration pages that have no steps of their own.')
                        ->schema([
                            ...self::sectionHeading(),
                            Textarea::make('intro')->label('Line under the heading')->rows(2)->maxLength(200)->columnSpanFull(),
                            IconAlign::make(default: 'center')->helperText('Applies to every step on the home page. On phones the steps always run down the left side.'),
                            self::tiles('items', 'Steps')->helperText('Four steps fit one row. Keep titles to a word or two and texts to a similar length so the columns line up.')->maxItems(6)->columnSpanFull(),
                        ]),
                    Section::make('Steps for testing services')->statePath('testingProcess')->compact()
                        ->description('Shown on product testing pages instead of the steps above. Not shown on the home page.')
                        ->schema([
                            self::tiles('items', 'Steps')->maxItems(6),
                        ]),
                    Section::make('Why choose us')->statePath('whyUs')->compact()->columns(2)->schema([
                        ...self::sectionHeading(link: true),
                        Textarea::make('intro')->rows(2)->maxLength(300)->columnSpanFull(),
                        IconAlign::make(),
                        self::tiles('items', 'Reasons')->maxItems(6)->columnSpanFull(),
                    ]),
                ]),

                Tab::make('Stories & promos')->icon(Heroicon::OutlinedMegaphone)->schema([
                    Section::make('Client stories')->statePath('testimonials')->compact()->columns(2)
                        ->description('The testimonials themselves are managed under Website → Testimonials.')
                        ->schema(self::sectionHeading()),
                    Section::make('Audits & events')->statePath('fieldwork')->compact()->columns(2)
                        ->description('Shown after the industries. Lists the 3 newest articles from Audits & inspections and the next 4 upcoming trade fairs automatically.')
                        ->schema([
                            ...self::sectionHeading(),
                            TextInput::make('intro')->label('Line under the heading')->maxLength(160)->columnSpanFull(),
                        ]),
                    Section::make('Promo cards')->statePath('promos')->compact()->schema([
                        Toggle::make('visible')->label('Show this section'),
                        Repeater::make('items')->hiddenLabel()->schema([
                            Grid::make(2)->schema([
                                TextInput::make('eyebrow')->label('Small label')->maxLength(50),
                                IconPicker::make(),
                                ImageUpload::icon('iconImage', 'icons/home')->columnSpanFull(),
                                TextInput::make('title')->required()->maxLength(90)->columnSpanFull(),
                                Textarea::make('text')->rows(2)->maxLength(200)->columnSpanFull(),
                                TextInput::make('href')->label('Link')->required()->placeholder('/resources/events'),
                                TextInput::make('imageAlt')->label('Describe the photo'),
                                ImageUpload::make('image', 'home')->label('Photo')->columnSpanFull()
                                    ->helperText('Optional. The first two cards keep their built-in photo when empty; other cards show the icon instead.'),
                            ]),
                        ])->grid(2)->maxItems(4)->reorderableWithDragAndDrop()->addActionLabel('Add card')
                            ->itemLabel(fn (array $state) => $state['title'] ?? null),
                    ]),
                ]),

                Tab::make('FAQ')->icon(Heroicon::OutlinedQuestionMarkCircle)->statePath('faq')->schema([
                    Section::make()->compact()->columns(2)->schema([
                        ...self::sectionHeading(),
                        TextInput::make('intro')->label('Line under the heading')->maxLength(120)->helperText('Followed by an "Ask a specialist" link.'),
                    ]),
                    Repeater::make('items')->label('Questions')->schema([
                        TextInput::make('q')->label('Question')->required()->maxLength(160),
                        Textarea::make('a')->label('Answer')->required()->rows(3)->maxLength(800),
                    ])->collapsed()->reorderableWithDragAndDrop()->addActionLabel('Add question')
                        ->itemLabel(fn (array $state) => $state['q'] ?? null)
                        ->helperText('Also published to Google as FAQ structured data.'),
                ]),

                Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->statePath('seo')->schema([
                    TextInput::make('title')->label('Browser and Google title')->required()->maxLength(70)
                        ->hint(fn (?string $state) => mb_strlen((string) $state).'/70'),
                    Textarea::make('description')->label('Google description')->required()->rows(3)->maxLength(160)
                        ->hint(fn (?string $state) => mb_strlen((string) $state).'/160'),
                    RichSnippetFields::make(),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save home page')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $setting = Setting::firstOrNew(['key' => 'home']);
        $setting->value = HomePage::merge(HomePage::settings(), $this->form->getState());
        $setting->save();

        Notification::make()->success()->title('Home page saved')->body('The website shows the changes after its next build.')->send();
    }

    /** @return list<mixed> */
    private static function sectionHeading(bool $link = false): array
    {
        return [
            Toggle::make('visible')->label('Show this section')->columnSpanFull(),
            TextInput::make('eyebrow')->label('Small label above the heading')->maxLength(40),
            TextInput::make('heading')->required()->maxLength(90),
            ...($link ? [
                TextInput::make('linkLabel')->label('Link text')->maxLength(40),
                TextInput::make('linkUrl')->label('Link')->placeholder('/services'),
            ] : []),
        ];
    }

    private static function tiles(string $name, string $label, bool $withLink = false): Repeater
    {
        return Repeater::make($name)->label($label)
            ->schema([
                TextInput::make('title')->required()->maxLength(60),
                IconPicker::make(),
                ImageUpload::icon('iconImage', 'icons/home')->columnSpanFull(),
                Textarea::make('text')->rows(2)->maxLength(240)->columnSpanFull(),
                ...($withLink ? [TextInput::make('href')->label('Link')->required()->placeholder('/certification/india')->columnSpanFull()] : []),
            ])
            ->columns(2)->collapsed()->reorderableWithDragAndDrop()->addActionLabel('Add tile')
            ->itemLabel(fn (array $state) => $state['title'] ?? null);
    }
}
