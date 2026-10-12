<?php

namespace App\Filament\Pages;

use App\Filament\Components\IconAlign;
use App\Filament\Components\IconPicker;
use App\Filament\Components\ImageUpload;
use App\Filament\Resources\Offices\OfficeResource;
use App\Models\Setting;
use App\Support\Careers;
use App\Support\Images;
use App\Support\Privacy;
use App\Support\TestCategories;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changePassword')
                ->label('Change password')
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->url(fn () => filament()->getProfileUrl()),
        ];
    }

    public function mount(): void
    {
        $this->form->fill([...Setting::get('site', []), 'privacy' => Privacy::settings(), 'careers' => Careers::settings()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Company')->icon('heroicon-o-building-office')->columns(2)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('tagline'),
                    Textarea::make('description')->rows(2)->columnSpanFull()->helperText('Default meta description and footer blurb.'),
                    TextInput::make('url')->label('Website URL')->url()->required(),
                ]),
                Tab::make('Branding')->icon('heroicon-o-swatch')->schema([
                    ImageUpload::make('logo', 'branding', maxWidth: 840)->label('Website logo')
                        ->imageEditor(false)->imagePreviewHeight('72')
                        ->helperText('Shown in the header and given to Google as the company logo. A wide SVG, or a transparent PNG or WebP about 420 × 72 px. Leave empty to use the default Instacertify logo.'),
                    FileUpload::make('favicon')->label('Favicon')
                        ->disk(config('instacertify.media_disk'))
                        ->directory('branding')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'])
                        ->maxSize(1024)
                        ->imagePreviewHeight('96')
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => Images::storeFavicon($file->get())
                            ?? throw ValidationException::withMessages(['data.favicon' => 'This file is not a readable PNG, JPG, WebP or ICO image.']))
                        ->helperText('The small icon in browser tabs, bookmarks, Google search results and the admin panel. Upload a square PNG of at least 512 × 512 pixels (ICO also works). Empty transparent edges are trimmed and it is fitted into a square automatically, without cropping. Leave empty to use the default Instacertify icon.'),
                ]),
                Tab::make('Contact')->icon('heroicon-o-phone')->columns(2)->statePath('contact')->schema([
                    TextInput::make('phone')->label('Phone (display)')->required(),
                    TextInput::make('phoneE164')->label('Phone (for links)')->placeholder('+919999118039')->required(),
                    TextInput::make('whatsapp')->label('WhatsApp number')->placeholder('919999118039')->required(),
                    TextInput::make('email')->email()->required(),
                    TextInput::make('address')->columnSpanFull(),
                    TextInput::make('hours')->label('Business hours'),
                    TextInput::make('responseTime')->label('Response promise')->placeholder('We reply within 1 business day'),
                ]),
                Tab::make('Social & rating')->icon('heroicon-o-share')->schema([
                    Text::make(new HtmlString('Office locations and their maps are managed under <a href="'.e(OfficeResource::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Website → Offices</a>.')),
                    Section::make('Social media icons')->statePath('social')->compact()->columns(2)
                        ->description('Shown as icons in the website footer, in this order, and shared with Google as the company\'s official profiles. Leave a field empty to hide that icon.')
                        ->schema([
                            self::socialField('linkedin', 'LinkedIn', 'https://www.linkedin.com/company/instacertify', ['linkedin.com']),
                            self::socialField('youtube', 'YouTube', 'https://www.youtube.com/@instacertify', ['youtube.com', 'youtu.be']),
                            self::socialField('instagram', 'Instagram', 'https://www.instagram.com/instacertify/', ['instagram.com']),
                            self::socialField('facebook', 'Facebook', 'https://www.facebook.com/instacertify', ['facebook.com', 'fb.com', 'fb.me']),
                            self::socialField('twitter', 'X (Twitter)', 'https://x.com/instacertify', ['x.com', 'twitter.com']),
                            self::socialField('pinterest', 'Pinterest', 'https://pinterest.com/instacertify', ['pinterest.com', 'pinterest.co.in', 'pin.it']),
                        ]),
                    Section::make('Google rating shown in search results')->statePath('rating')->compact()->columns(2)
                        ->description('Also shown with client photos under the buttons at the top of service and ad landing pages. Keep it in step with your Google Business Profile.')
                        ->schema([
                            TextInput::make('value')->label('Average rating')->numeric()->minValue(1)->maxValue(5)->step(0.1),
                            TextInput::make('count')->label('Number of reviews')->numeric(),
                            TextInput::make('url')->label('Link to your Google reviews')->url()->columnSpanFull()
                                ->placeholder('https://g.page/r/…/review')
                                ->helperText('From Google Business Profile → Read reviews. With a Google link the rating shows the Google logo and opens your reviews; without one it links to the reviews on the page.'),
                        ]),
                ]),
                Tab::make('Testing industries')->icon('heroicon-o-beaker')->statePath('testCategories')->schema([
                    Text::make('Icons for the industry tiles on the Product testing page. Which tests sit in each industry is set on each testing service.'),
                    IconAlign::make(),
                    Grid::make(['default' => 1, 'lg' => 2])->schema(collect(TestCategories::ALL)->map(fn (array $category, string $slug) => Fieldset::make($category['label'])
                        ->statePath($slug)->columns(1)->schema([
                            IconPicker::make()->placeholder($category['icon'])
                                ->helperText("Leave empty for the default icon ({$category['icon']})."),
                            ImageUpload::icon('iconImage', 'icons/testing'),
                        ]))->values()->all()),
                ]),
                Tab::make('Homepage')->icon('heroicon-o-home')->schema([
                    Text::make(new HtmlString('The hero, tiles, icons, client logos, FAQs and every other part of the front page are edited under <a href="'.e(ManageHomePage::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Website → Home page</a>.')),
                ]),
                Tab::make('Popup')->icon('heroicon-o-window')->columns(2)->statePath('popup')->schema([
                    Toggle::make('enabled')->label('Open the enquiry popup automatically')->columnSpanFull()
                        ->helperText('Desktop only: opens after the delay or when the visitor moves to leave. Never shown again for 7 days once closed, or 30 days after an enquiry. The "Get a free quote" buttons always open it.'),
                    TextInput::make('title')->placeholder('Same as the Enquiry form tab'),
                    TextInput::make('subtitle')->placeholder('Same as the Enquiry form tab'),
                    TextInput::make('button')->label('Button text')->placeholder('Same as the Enquiry form tab'),
                    TextInput::make('delay')->label('Delay (seconds)')->numeric()->minValue(5)->default(25),
                    Section::make('Side image')->compact()->columns(2)->columnSpanFull()
                        ->description('Shown to the left of the form on tablets, laptops and monitors. Hidden on phones so the form fits the screen. Leave empty for the form on its own.')
                        ->schema([
                            ImageUpload::make('image', 'popup', maxWidth: 1200)->label('Image')
                                ->imageEditorAspectRatios(['4:5', '3:4', '2:3', null])
                                ->imagePreviewHeight('220')
                                ->helperText('A portrait photo of at least 800 × 1000 px works best; it is cropped to fill the panel. Use your own or licensed photos, not AI-generated people presented as your team.'),
                            Grid::make(1)->schema([
                                TextInput::make('imageAlt')->label('Image description (alt text)')->maxLength(150)
                                    ->placeholder('e.g. Engineer checking a product label in our Noida office')
                                    ->helperText('Read out by screen readers. Leave empty if the image is only decoration.'),
                                ToggleButtons::make('imageFocus')->label('Keep in view when cropped')->inline()->default('center')
                                    ->options(['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'])
                                    ->helperText('The panel is tall and narrow, so the sides of most photos are trimmed. Pick the side with the person or product that must stay visible.'),
                            ]),
                        ]),
                ]),
                Tab::make('Enquiry form')->icon('heroicon-o-chat-bubble-left-right')->statePath('leadForm')->schema([
                    Text::make('The enquiry form shown on service, article, standard, event and industry pages, at the foot of most pages and on the contact page. Leave a field empty to use the wording shown in grey. The homepage hero, the popup and ad landing pages can set their own heading.'),
                    Section::make('Heading and button')->compact()->columns(2)->schema([
                        TextInput::make('title')->label('Heading')->placeholder('Free Expert Assessment')->maxLength(70),
                        TextInput::make('button')->label('Button text')->placeholder('Get free assessment')->maxLength(40),
                        TextInput::make('subtitle')->label('Line under the heading')->placeholder('Free assessment. We reply within 1 business day.')->maxLength(160)->columnSpanFull(),
                        TextInput::make('privacyNote')->label('Note under the button (optional)')->placeholder('e.g. No spam, ever.')->maxLength(160)->columnSpanFull()
                            ->helperText('Leave empty to show nothing. Data handling is explained in the Privacy Policy linked from the consent checkbox.'),
                    ]),
                    Section::make('Field labels')->compact()->columns(2)->collapsed()->schema([
                        TextInput::make('nameLabel')->label('Name')->placeholder('Name')->maxLength(40),
                        TextInput::make('phoneLabel')->label('Phone')->placeholder('Phone / WhatsApp')->maxLength(40),
                        TextInput::make('emailLabel')->label('Email')->placeholder('Email')->maxLength(40),
                        TextInput::make('productLabel')->label('Product')->placeholder('Product or requirement')->maxLength(40),
                        TextInput::make('productPlaceholder')->label('Example shown in the product field')->placeholder('e.g. Bluetooth speaker, BIS + WPC')->maxLength(80)->columnSpanFull(),
                    ]),
                    Section::make('Contact page')->compact()->columns(2)->collapsed()
                        ->description('The two-step form on /contact. Empty fields use the heading and line above.')
                        ->schema([
                            TextInput::make('contactTitle')->label('Heading')->placeholder('Free Expert Assessment')->maxLength(70),
                            TextInput::make('contactButton')->label('Button text')->placeholder('Get my free quote')->maxLength(40),
                            TextInput::make('contactSubtitle')->label('Line under the heading')->placeholder('Takes under a minute. Two quick steps.')->maxLength(160)->columnSpanFull(),
                        ]),
                    Section::make('After sending')->compact()->collapsed()->schema([
                        TextInput::make('successTitle')->label('Thank-you heading')->placeholder('Thank you — we\'ve got it.')->maxLength(70),
                        Textarea::make('successText')->label('Thank-you message')->rows(2)->placeholder('A certification specialist will contact you within 1 business day, usually much sooner.')->maxLength(300),
                    ]),
                ]),
                Tab::make('Internal links')->icon('heroicon-o-link')->schema([
                    Toggle::make('autolinksEnabled')->label('Automatically link keywords in articles and service pages')->default(true)
                        ->helperText('Only the first mention of each keyword is linked, never inside headings or existing links, and never to the page itself.'),
                    TextInput::make('autolinksMax')->label('Maximum automatic links per page')->numeric()->minValue(1)->maxValue(20)->default(6),
                    Repeater::make('autolinks')->label('Keywords')
                        ->helperText('Comma-separate variations. Phrases written in ALL CAPS (e.g. REACH, WPC) only match in capitals, so ordinary words are not linked.')
                        ->schema([
                            TextInput::make('keywords')->required()->placeholder('BIS CRS, CRS registration')->columnSpan(2),
                            TextInput::make('url')->label('Links to')->required()->placeholder('/certification/india/bis-crs-mark-certification')
                                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                    if (! preg_match('#^(/(?!/)|https?://(www\.)?instacertify\.com(/|$))\S*$#i', trim((string) $value))) {
                                        $fail('Internal links only: a path on this site such as /certification/india/bis-crs-mark-certification.');
                                    }
                                }),
                        ])
                        ->columns(3)->collapsed()->reorderable()->addActionLabel('Add keyword')
                        ->itemLabel(fn (array $state) => isset($state['keywords']) ? str($state['keywords'])->limit(60).' → '.($state['url'] ?? '') : null)
                        ->defaultItems(0),
                ]),
                Tab::make('Careers page')->icon('heroicon-o-briefcase')->statePath('careers')->schema([
                    TextInput::make('headline')->required()->maxLength(90)->default(Careers::DEFAULTS['headline']),
                    Textarea::make('intro')->rows(3)->required()->maxLength(400)->default(Careers::DEFAULTS['intro']),
                    Repeater::make('perks')->label('Why work with us')
                        ->schema([
                            TextInput::make('title')->required()->maxLength(60),
                            Textarea::make('text')->rows(2)->required()->maxLength(200),
                        ])
                        ->columns(2)->reorderable()->maxItems(6)->default(Careers::DEFAULTS['perks'])
                        ->itemLabel(fn (array $state) => $state['title'] ?? null),
                    Toggle::make('generalApplications')->label('Accept CVs without a specific vacancy')->default(true)
                        ->helperText('Shows a "Send us your CV" form on the careers page, even when no positions are open.'),
                    Text::make('Open, edit and close vacancies under Careers → Vacancies.'),
                ]),
                Tab::make('Privacy & cookies')->icon('heroicon-o-shield-check')->columns(2)->statePath('privacy')->schema([
                    TextInput::make('version')->label('Privacy / cookie policy version')->required()->default(Privacy::DEFAULTS['version'])->maxLength(20)
                        ->helperText('Change this (e.g. 2026-11) whenever you update the Privacy or Cookie Policy. Every visitor is then asked for cookie consent again, and new enquiries record this version.'),
                    TextInput::make('grievanceOfficer')->label('Grievance Officer / DPO name')->required()->default(Privacy::DEFAULTS['grievanceOfficer'])
                        ->helperText('Shown in the cookie notice. DPDP Act s. 8(9) and GDPR Art. 13 require a named contact for privacy requests.'),
                    TextInput::make('grievanceEmail')->label('Privacy contact email')->email()->required()->default(Privacy::DEFAULTS['grievanceEmail']),
                    Section::make('Retention — applied automatically every night')->compact()->columns(2)->columnSpanFull()->schema([
                        TextInput::make('consentLogMonths')->label('Keep cookie consent records')->numeric()->minValue(12)->suffix('months')
                            ->default(Privacy::DEFAULTS['consentLogMonths'])->helperText('Minimum 12 (proof of consent). The visitor\'s IP address is deleted with the record. Visitors are told this period in the cookie settings and the Cookie Policy.'),
                        TextInput::make('leadMonths')->label('Anonymise closed leads after')->numeric()->minValue(6)->suffix('months')
                            ->default(Privacy::DEFAULTS['leadMonths'])->helperText('Won/lost leads untouched for this long lose name, email, phone and notes.'),
                        TextInput::make('ipDays')->label('Remove enquiry IP addresses after')->numeric()->minValue(30)->suffix('days')
                            ->default(Privacy::DEFAULTS['ipDays'])->helperText('Kept for spam/fraud checks and as evidence of consent. Visitors are told this period under every enquiry form.'),
                        TextInput::make('applicationMonths')->label('Delete CVs & anonymise job applications after')->numeric()->minValue(1)->maxValue(36)->suffix('months')
                            ->default(Privacy::DEFAULTS['applicationMonths'])->helperText('Counted from the last update. Candidates are told this period when they apply.'),
                    ]),
                ]),
                Tab::make('Tracking & verification')->icon('heroicon-o-chart-bar')->statePath('integrations')->schema([
                    Text::make('Every tool below loads only after the visitor accepts the matching cookie category (analytics or marketing), and is listed in the cookie notice and Cookie Policy automatically. Leave a field empty to switch that tool off. Changes go live with the next website build.'),
                    Section::make('Analytics & tag management')->compact()->columns(2)->schema([
                        self::trackingId('gtm', 'Google Tag Manager container ID', 'GTM-XXXXXXX', '/^GTM-[A-Z0-9]{4,12}$/')
                            ->helperText('Tag Manager → the ID next to the container name. Loads after analytics or marketing consent, with Google Consent Mode set to the visitor\'s choice.'),
                        self::trackingId('googleTag', 'Google Analytics 4 measurement ID', 'G-XXXXXXXXXX', '/^(G|GT)-[A-Z0-9]{4,15}$/')
                            ->helperText('Analytics → Admin → Data streams → your web stream. Leave empty if GA4 is already set up inside Tag Manager, or visits are counted twice.'),
                        self::trackingId('clarity', 'Microsoft Clarity project ID', 'abcd1234ef', '/^[a-z0-9]{6,20}$/', upper: false)
                            ->helperText('Clarity → Settings → Overview → Project ID. Session recordings and heatmaps; loads after analytics consent.'),
                        TextInput::make('matomoSiteId')->label('Matomo site ID')->numeric()->placeholder('1')
                            ->helperText('Only used when the self-hosted Matomo server is configured.'),
                    ]),
                    Section::make('Advertising & conversions')->compact()->columns(2)->schema([
                        self::trackingId('googleAds', 'Google Ads tag ID', 'AW-123456789', '/^AW-\d{6,15}$/')
                            ->helperText('Google Ads → Goals → Conversions → your lead action → Tag setup.'),
                        TextInput::make('googleAdsLeadLabel')->label('Google Ads lead conversion label')->placeholder('AbC-D_efGhIjKlMnOp')
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim(Str::afterLast($state, '/')) : null)
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                if (filled($value) && ! preg_match('/^[A-Za-z0-9_-]{6,40}$/', trim(Str::afterLast((string) $value, '/')))) {
                                    $fail('Paste the conversion label, e.g. AbC-D_efGhIjKlMnOp.');
                                }
                            })
                            ->helperText('The part after the slash in "AW-123456789/AbC-D_efG". Counted each time an enquiry form is sent.'),
                        self::trackingId('metaPixel', 'Meta (Facebook) Pixel ID', '123456789012345', '/^\d{10,20}$/')
                            ->helperText('Meta Events Manager → Data sources → your pixel. Sends a "Lead" event when an enquiry is sent.'),
                        self::trackingId('linkedinPartner', 'LinkedIn Insight Tag partner ID', '1234567', '/^\d{4,12}$/')
                            ->helperText('LinkedIn Campaign Manager → Analyze → Insight Tag.'),
                    ]),
                    Section::make('Site ownership verification')->compact()->columns(2)
                        ->description('Paste the code or the whole <meta> tag the tool gives you; only the code is kept. Not needed if you verified through DNS.')
                        ->schema([
                            self::verification('googleVerification', 'Google Search Console', 'google-site-verification'),
                            self::verification('bingVerification', 'Bing Webmaster Tools', 'msvalidate.01'),
                            self::verification('yandexVerification', 'Yandex Webmaster', 'yandex-verification'),
                            self::verification('pinterestVerification', 'Pinterest', 'p:domain_verify'),
                            self::verification('facebookVerification', 'Meta (Facebook) domain verification', 'facebook-domain-verification'),
                        ]),
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
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $setting = Setting::firstOrNew(['key' => 'site']);
        $uploads = ['favicon', 'logo', 'popup.image'];
        $previous = collect($uploads)->mapWithKeys(fn (string $key) => [$key => Arr::get($setting->value ?? [], $key)]);
        $setting->value = array_replace($setting->value ?? [], $this->form->getState());
        $setting->save();

        foreach ($previous as $key => $path) {
            if ($path && $path !== Arr::get($setting->value, $key)) {
                Images::disk()->delete($path);
            }
        }

        Notification::make()->success()->title('Settings saved')->body('The website picks this up on its next build.')->send();
    }

    private static function trackingId(string $name, string $label, string $example, string $pattern, bool $upper = true): TextInput
    {
        return TextInput::make($name)->label($label)->placeholder($example)->maxLength(40)
            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? ($upper ? strtoupper(trim($state)) : strtolower(trim($state))) : null)
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($pattern, $upper, $example) {
                $value = $upper ? strtoupper(trim((string) $value)) : strtolower(trim((string) $value));
                if (filled($value) && ! preg_match($pattern, $value)) {
                    $fail("This does not look like a valid ID. It should look like {$example}.");
                }
            });
    }

    private static function verification(string $name, string $label, string $metaName): TextInput
    {
        $code = fn (?string $state) => filled($state)
            ? trim(preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $state, $m) ? $m[1] : $state)
            : null;

        return TextInput::make($name)->label($label)->placeholder("<meta name=\"{$metaName}\" content=\"…\">")->maxLength(300)
            ->dehydrateStateUsing($code)
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($code) {
                if (filled($value) && ! preg_match('/^[A-Za-z0-9_\-.=+\/]{6,120}$/', (string) $code($value))) {
                    $fail('Paste just the verification code, or the full <meta> tag.');
                }
            });
    }

    /** @param  list<string>  $hosts */
    private static function socialField(string $name, string $label, string $example, array $hosts): TextInput
    {
        return TextInput::make($name)->label($label)->url()->maxLength(255)->placeholder($example)
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($label, $hosts) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $matches = collect($hosts)->contains(fn ($h) => $host === $h || str_ends_with($host, ".{$h}"));
                if (filled($value) && (! str_starts_with((string) $value, 'https://') || ! $matches)) {
                    $fail("Enter the full https:// link to your {$label} page (e.g. https://{$hosts[0]}/…).");
                }
            });
    }
}
