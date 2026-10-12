<?php

namespace App\Filament\Resources\LandingPages;

use App\Filament\Components\IconAlign;
use App\Filament\Components\IconPicker;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Filament\Pages\ManageHomePage;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\LandingPage;
use App\Models\Post;
use App\Models\Scheme;
use App\Models\Service;
use App\Support\SchemeClassifier;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class LandingPageResource extends Resource
{
    protected static ?string $model = LandingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Landing pages';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Page setup')
                    ->description('One page per ad group works best: the headline should repeat the keyword people searched for.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->label('Internal name')->required()->maxLength(120)
                            ->helperText('Only shown in the admin, e.g. "Google Ads – BIS certification".'),
                        TextInput::make('path')->label('URL')->required()->maxLength(120)
                            ->prefix('instacertify.com/')
                            ->placeholder('lp/bis-certification')
                            ->helperText('Start with lp/ to keep ad pages apart from the main site.')
                            ->regex('/^[a-z0-9]+(?:[-\/][a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Use lowercase letters, numbers and hyphens only.'])
                            ->rule(fn (?LandingPage $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                                if (LandingPage::pathTaken((string) $value, $record?->getKey())) {
                                    $fail('Another page already uses this URL.');
                                }
                            }),
                        ToggleButtons::make('status')->options(LandingPage::STATUSES)->inline()->default('draft')->required()
                            ->colors(['draft' => 'gray', 'published' => 'success']),
                        Select::make('service_path')->label('Service for leads')->searchable()
                            ->options(fn () => Service::orderBy('title')->pluck('title', 'path'))
                            ->helperText('Enquiries from this page are tagged with this service.'),
                        Toggle::make('indexable')->label('Show in Google search results')->default(false)
                            ->helperText('Keep off for ad-only pages so they don’t compete with your main pages in search. Google Ads can still use the page.')
                            ->columnSpanFull(),
                    ]),

                Group::make()->statePath('content')->schema([
                    Tabs::make()->vertical()->persistTabInQueryString()->tabs([
                        Tab::make('1. Top of page')->icon(Heroicon::OutlinedSparkles)->statePath('hero')->columns(2)->schema([
                            self::note('Headline, ticks and buttons on the left; the enquiry form on the right. On phones the form comes straight after the buttons.'),
                            TextInput::make('headline')->label('Headline (H1)')->required()->maxLength(80)->columnSpanFull()->live(onBlur: true)
                                ->helperText('Repeat the searched keyword in plain words, e.g. "BIS Certification for Your Product – ISI & CRS".'),
                            Textarea::make('subheadline')->rows(2)->maxLength(240)->columnSpanFull()->live(onBlur: true)
                                ->helperText('One or two sentences: who it is for and what they get. Use the keyword once here too.'),
                            Repeater::make('points')->label('Key points (ticks under the headline)')
                                ->simple(TextInput::make('text')->required()->maxLength(90))
                                ->maxItems(4)->defaultItems(0)->reorderable()->columnSpanFull(),
                            TextInput::make('price_note')->label('Price line')->maxLength(120)
                                ->placeholder('BIS fee from ₹7,000 for CRS')->helperText('Bold orange line under the ticks.'),
                            TextInput::make('reply_note')->label('Response promise')->maxLength(80)
                                ->placeholder('We reply within 30 minutes in office hours')->helperText('Small line under the buttons.'),
                            TextInput::make('cta')->label('Main button text')->maxLength(40)->placeholder('Get my free quote')
                                ->helperText('Used on the top button, the form button and the closing button.'),
                            Section::make('Enquiry form')->compact()->columns(2)->columnSpanFull()->schema([
                                TextInput::make('form_title')->label('Form heading')->maxLength(80)->placeholder('Get a free quote'),
                                TextInput::make('form_note')->label('Line under the form')->maxLength(80)->helperText('Optional. Leave empty to show nothing under the form.'),
                                TextInput::make('form_subtitle')->label('Text under the form heading')->maxLength(140)->columnSpanFull()
                                    ->placeholder('Itemised quote with fees, testing and timeline. No obligation.'),
                            ]),
                        ]),

                        Tab::make('2. Trust strip')->icon(Heroicon::OutlinedCheckBadge)->schema([
                            self::note('Right under the top section: up to 4 short facts in outlined boxes with an icon, then the moving strip of client logos. The star rating sits under the headline.'),
                            IconAlign::make('stats_icon_align'),
                            Repeater::make('stats')->label('Numbers')->columns(3)->maxItems(4)->defaultItems(0)->reorderable()->schema([
                                TextInput::make('value')->required()->maxLength(20)->placeholder('42,818+'),
                                TextInput::make('label')->required()->maxLength(40)->placeholder('Happy customers'),
                                IconPicker::make()->placeholder('Picked from the label')
                                    ->helperText('Optional. Left empty, the icon is picked from the label: people for customers, a pin for offices, a gavel for "mandatory".'),
                                ImageUpload::icon('iconImage', 'icons/landing')->columnSpanFull(),
                            ]),
                            Group::make()->statePath('trust')->columns(2)->schema([
                                TextInput::make('logos_title')->label('Heading above the logos')->maxLength(80)
                                    ->placeholder('Trusted by leading brands across the globe'),
                                Text::make(new HtmlString('The logos themselves are shared by every page and edited under <a href="'.e(ManageHomePage::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Website → Home page → Client logos</a>.'))
                                    ->color('gray'),
                                Toggle::make('hide_logos')->label('Hide the client logos on this page'),
                                Toggle::make('hide_rating')->label('Hide the star rating under the buttons')
                                    ->helperText('The rating and its link come from Site settings → Social & rating; the faces from Website → Testimonials.'),
                            ]),
                        ]),

                        Tab::make('3. BIS fee finder')->icon(Heroicon::OutlinedMagnifyingGlass)->statePath('finder')->columns(2)->schema([
                            self::note('Search box for BIS products with their fees, right under the numbers. Only shown when switched on.'),
                            Toggle::make('enabled')->label('Show the BIS product fee finder')->columnSpanFull()
                                ->helperText('Visitors search ISI and CRS products by name, IS number or HSN code and see the BIS fee and date. The full fee breakdown opens after they leave their details. Consulting prices are never shown.'),
                            TextInput::make('heading')->maxLength(90)->placeholder('Check the BIS fee for your product'),
                            TextInput::make('consulting_note')->label('Consulting fee line')->maxLength(60)->placeholder('Best-value quote – let’s talk'),
                            Textarea::make('intro')->rows(2)->maxLength(240)->columnSpanFull(),
                            TagsInput::make('suggestions')->label('Popular searches')->placeholder('Add a product and press Enter')
                                ->splitKeys(['Enter', ','])->columnSpanFull(),
                            Section::make('CRS fees')->description('The same for every CRS product.')->compact()->columns(4)->columnSpanFull()->schema([
                                TextInput::make('crs_fee_micro')->label('Micro')->numeric()->minValue(0)->prefix('₹')->placeholder('7000'),
                                TextInput::make('crs_fee_small')->label('Small')->numeric()->minValue(0)->prefix('₹')->placeholder('14500'),
                                TextInput::make('crs_fee_medium')->label('Medium')->numeric()->minValue(0)->prefix('₹')->placeholder('27000'),
                                TextInput::make('crs_fee_large')->label('Large and foreign')->numeric()->minValue(0)->prefix('₹')->placeholder('27000'),
                                TextInput::make('crs_test_from')->label('Testing from')->numeric()->minValue(0)->prefix('₹')->placeholder('20000'),
                            ]),
                            Section::make('ISI: other BIS charges')->description('Paid on every ISI mark product by Indian manufacturers. Foreign manufacturers see “Get a detailed quote”. Minimum marking fees come from each product in Standards.')
                                ->compact()->columns(3)->columnSpanFull()->schema([
                                    TextInput::make('isi_application_fee')->label('Application charge')->numeric()->minValue(0)->prefix('₹')->placeholder('1000'),
                                    TextInput::make('isi_licence_fee')->label('Licence fee')->numeric()->minValue(0)->prefix('₹')->placeholder('1000'),
                                    TextInput::make('isi_audit_fee')->label('Audit charges')->numeric()->minValue(0)->prefix('₹')->placeholder('7000'),
                                ]),
                        ]),

                        Tab::make('4. About the service')->icon(Heroicon::OutlinedPhoto)->statePath('intro')->columns(2)->schema([
                            self::note('A short explainer with a photo beside it, e.g. "What is BIS certification?". Answers the question behind the search before the options. Hidden when the text is empty.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('What is BIS certification?')->columnSpanFull()
                                ->helperText('Use the keyword. A question works well because it matches how people search.'),
                            Textarea::make('text')->rows(4)->maxLength(700)->columnSpanFull()
                                ->helperText('Two to four plain sentences. Leave an empty line between paragraphs.'),
                            Repeater::make('points')->label('Key facts (ticks)')->simple(TextInput::make('text')->required()->maxLength(120))
                                ->maxItems(5)->defaultItems(0)->reorderable()->columnSpanFull(),
                            ImageUpload::make('image', 'landing', maxWidth: 1400)->label('Photo')->columnSpanFull()
                                ->imagePreviewHeight('160')
                                ->helperText('A real photo works best: your team on site, a test in the lab, a product with its certificate. Landscape, at least 1200 px wide. Leave empty to show the text full width.'),
                            TextInput::make('image_alt')->label('Photo description (alt text)')->maxLength(140)
                                ->requiredWith('image')
                                ->helperText('What the photo shows, for Google Images and screen readers. Required with a photo.'),
                            TextInput::make('image_caption')->label('Caption under the photo')->maxLength(120),
                        ]),

                        Tab::make('5. Options')->icon(Heroicon::OutlinedSquares2x2)->statePath('options')->schema([
                            self::note('Up to 3 side-by-side boxes, each with its own quote button. Hidden when there are no options.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('Which option do you need?'),
                            Textarea::make('intro')->rows(2)->maxLength(240),
                            TextInput::make('price_label')->label('Price column heading')->maxLength(30)->placeholder('Consulting')
                                ->helperText('E.g. "All inclusive" when the price covers government charges too.'),
                            IconAlign::make('icon_align'),
                            Repeater::make('items')->label('Option cards')->columns(2)->maxItems(3)->defaultItems(0)->reorderable()
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)->collapsible()
                                ->schema([
                                    TextInput::make('title')->required()->maxLength(60)->placeholder('ISI Mark')->columnSpanFull()
                                        ->helperText('Its button reads "Get my … quote".'),
                                    Section::make('Heading icon')->icon(Heroicon::OutlinedPhoto)->compact()->columns(2)->columnSpanFull()
                                        ->description('Shown beside the title. Use one: a mark or logo image, your own icon, or a Google icon. The image wins when more than one is set.')
                                        ->schema([
                                            ImageUpload::make('image', 'landing/marks', 480)->label('Mark or logo image')->imageEditor(false)->imagePreviewHeight('96')
                                                ->helperText('E.g. the ISI mark or the BIS Standard Mark. Official artwork only – BIS treats look-alike marks as spurious. PNG or SVG on white or transparent.'),
                                            ImageUpload::icon('iconImage', 'icons/landing')
                                                ->helperText('Shown in a small tinted square. Transparent PNG, WebP or SVG; empty space around it is trimmed.'),
                                            IconPicker::make()->label('Or pick a Google icon'),
                                            TextInput::make('image_alt')->label('Image description (alt text)')->maxLength(120)->requiredWith('image')
                                                ->placeholder('ISI mark specimen'),
                                            TextInput::make('image_caption')->label('Line under the image')->maxLength(160)->columnSpanFull()
                                                ->placeholder('IS number above the mark, licence number CM/L-… below'),
                                        ]),
                                    TextInput::make('audience')->label('Who it is for')->maxLength(120)->placeholder('Steel, cement, appliances, toys…')->columnSpanFull(),
                                    TextInput::make('timeline')->maxLength(40)->placeholder('3–6 months'),
                                    TextInput::make('price')->label('Price')->maxLength(40)->placeholder('Best-value quote – let’s talk'),
                                    Repeater::make('points')->simple(TextInput::make('text')->required()->maxLength(100))
                                        ->maxItems(4)->defaultItems(0)->columnSpanFull(),
                                ]),
                        ]),

                        Tab::make('6. Us vs others')->icon(Heroicon::OutlinedScale)->statePath('compare')->schema([
                            self::note('Two columns: common problems with a cross, what we do with a tick. Hidden when the right column is empty.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('What we do differently'),
                            Textarea::make('intro')->rows(3)->maxLength(500),
                            TextInput::make('bad_title')->label('Left column heading')->maxLength(60)->placeholder('How it usually goes'),
                            Repeater::make('bad')->label('Left column: common problems (cross)')->simple(TextInput::make('text')->required()->maxLength(160))
                                ->maxItems(5)->defaultItems(0)->reorderable(),
                            TextInput::make('good_title')->label('Right column heading')->maxLength(60)->placeholder('How we do it'),
                            Repeater::make('good')->label('Right column: what we do instead (tick)')->simple(TextInput::make('text')->required()->maxLength(160))
                                ->maxItems(5)->defaultItems(0)->reorderable(),
                        ]),

                        Tab::make('7. How it works')->icon(Heroicon::OutlinedListBullet)->statePath('steps')->schema([
                            self::note('Numbered steps in a row, each with an icon. Hidden when empty.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('How it works'),
                            IconAlign::make('icon_align')->helperText('Applies to every step. On phones the steps always run down the left side.'),
                            Repeater::make('items')->label('Steps')->columns(2)->maxItems(5)->defaultItems(0)->reorderable()
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)->collapsible()
                                ->schema([
                                    TextInput::make('title')->required()->maxLength(60),
                                    IconPicker::make()->placeholder('Picked from the title')
                                        ->helperText('Optional. Left empty, the icon is picked from the title.'),
                                    Textarea::make('text')->rows(2)->maxLength(160)->columnSpanFull(),
                                    ImageUpload::icon('iconImage', 'icons/landing')->columnSpanFull(),
                                ]),
                        ]),

                        Tab::make('8. Photos of our work')->icon(Heroicon::OutlinedCamera)->statePath('work')->schema([
                            self::note('A row of real photos with captions after "How it works". Only audits tagged with this page’s certifications are shown, newest first; the section is hidden when none match and no photos are uploaded.'),
                            Toggle::make('hidden')->label('Hide this section on this page'),
                            TextInput::make('heading')->maxLength(120)->placeholder('Recent work by our team'),
                            Textarea::make('intro')->rows(2)->maxLength(240)
                                ->placeholder('Factory audits, inspections and certification projects we have handled, in India and abroad.'),
                            Select::make('schemes')->label('Show audits for these certifications')->multiple()
                                ->options(fn () => Scheme::options())
                                ->helperText(fn (Get $get) => self::matchingAudits($get('schemes'), $get('types')))
                                ->live(),
                            Select::make('types')->label('Type of work')->multiple()
                                ->options(SchemeClassifier::WORK_TYPES)->placeholder('Any type')
                                ->live(),
                            Select::make('posts')->label('Or pick audits by hand')->multiple()->searchable()->maxItems(6)
                                ->options(fn () => Post::published()->where('type', 'audit')->whereNotNull('image')->latest('published_at')->pluck('title', 'slug'))
                                ->helperText('Their photo and title are shown. Picked audits replace the certification filter above. Pick up to 6; otherwise the 3 newest matches are shown.'),
                            Repeater::make('photos')->label('Or upload your own photos')->columns(2)->maxItems(6)->defaultItems(0)->reorderable()
                                ->itemLabel(fn (array $state) => $state['caption'] ?? null)->collapsible()
                                ->helperText('Shown before the audits. Certificates handed over, lab tests, site visits, trade fairs.')
                                ->schema([
                                    ImageUpload::make('image', 'landing/work', maxWidth: 1200)->required()->columnSpanFull()->imagePreviewHeight('120'),
                                    TextInput::make('alt')->label('Photo description (alt text)')->required()->maxLength(140),
                                    TextInput::make('caption')->maxLength(90),
                                ]),
                        ]),

                        Tab::make('9. Video')->icon(Heroicon::OutlinedPlayCircle)->statePath('video')->columns(2)->schema([
                            self::note('One YouTube video after the photos. The page only loads YouTube when the visitor presses play, so it stays fast. Hidden when there is no link.'),
                            TextInput::make('url')->label('YouTube link')->url()->maxLength(200)->columnSpanFull()
                                ->placeholder('https://www.youtube.com/watch?v=…')
                                ->regex('#^https://(www\.|m\.)?(youtube\.com/(watch\?v=|shorts/|embed/)|youtu\.be/)[\w-]{11}#')
                                ->validationMessages(['regex' => 'Paste a YouTube video link.']),
                            TextInput::make('heading')->maxLength(120)->placeholder('See how it works in 2 minutes'),
                            TextInput::make('title')->label('Video title')->maxLength(100)
                                ->helperText('Read out by screen readers and used for Google’s video listing.'),
                            Textarea::make('text')->rows(2)->maxLength(240)->columnSpanFull(),
                        ]),

                        Tab::make('10. Why choose us')->icon(Heroicon::OutlinedStar)->statePath('benefits')->schema([
                            self::note('Up to 4 reasons, each with an icon, beside a photo. Hidden when empty.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('Why choose Instacertify'),
                            ...self::sectionPhoto('A photo of your lab, office or work on site, about 1200 × 900 px. Leave empty to show the reasons full width.'),
                            IconAlign::make('icon_align'),
                            Repeater::make('items')->label('Reasons')->columns(2)->maxItems(4)->defaultItems(0)->reorderable()
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)->collapsible()
                                ->schema([
                                    IconPicker::make(),
                                    TextInput::make('title')->required()->maxLength(60),
                                    Textarea::make('text')->rows(2)->maxLength(160)->columnSpanFull(),
                                    ImageUpload::icon('iconImage', 'icons/landing')->columnSpanFull(),
                                ]),
                        ]),

                        Tab::make('11. Documents')->icon(Heroicon::OutlinedDocumentText)->statePath('checklist')->schema([
                            self::note('Up to 2 plain lists, e.g. documents for importers and for manufacturers. Hidden when there are no lists.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('What you’ll need to send us'),
                            Textarea::make('intro')->rows(2)->maxLength(300),
                            Repeater::make('groups')->label('Lists')->columns(1)->maxItems(2)->defaultItems(0)->reorderable()
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)->collapsible()
                                ->schema([
                                    TextInput::make('title')->maxLength(80),
                                    Repeater::make('items')->simple(TextInput::make('text')->required()->maxLength(140))
                                        ->maxItems(10)->defaultItems(0)->reorderable(),
                                ]),
                            TextInput::make('note')->label('Note under the lists')->maxLength(200),
                            ...self::sectionPhoto('A photo of the documents or samples, about 1200 × 900 px. Leave empty to show the lists full width.'),
                        ]),

                        Tab::make('12. Reviews')->icon(Heroicon::OutlinedChatBubbleLeftRight)->statePath('reviews')->schema([
                            self::note('Three client reviews with their star ratings. Reviews that mention this page’s service come first.'),
                            Text::make(new HtmlString('Every page of the website shows reviews from one list, edited under <a href="'.e(TestimonialResource::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Website → Testimonials</a>. Add a review there and it appears on the home page, the service pages and every landing page the next time the website is rebuilt.'))
                                ->color('gray'),
                            Toggle::make('hidden')->label('Hide the reviews on this page'),
                            TextInput::make('heading')->maxLength(120)->placeholder('What clients say'),
                        ]),

                        Tab::make('13. Keywords')->icon(Heroicon::OutlinedHashtag)->statePath('related')->schema([
                            self::note('Products or phrases people search for alongside this service, shown as a short list with a "Product not listed?" link. Hidden when there are no keywords.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('Related products'),
                            Textarea::make('intro')->rows(2)->maxLength(240)
                                ->helperText('One plain sentence that can carry the other ways people search, e.g. "BIS registration", "ISI licence".'),
                            TagsInput::make('keywords')->placeholder('Add a keyword and press Enter')->splitKeys(['Enter', ',']),
                            TextInput::make('ask')->label('Link text after "Product not listed?"')->maxLength(60)->placeholder('Ask us – the check is free'),
                        ]),

                        Tab::make('14. FAQ')->icon(Heroicon::OutlinedQuestionMarkCircle)->statePath('faqs')->schema([
                            self::note('Heading and a "Still have a question?" call and WhatsApp box on the left, numbered questions on the right; the first one starts open. Up to 8, also sent to Google as FAQ structured data. Hidden when empty.'),
                            TextInput::make('heading')->maxLength(120)->placeholder('Common questions'),
                            Textarea::make('intro')->rows(2)->maxLength(200)->placeholder('Straight answers to what buyers ask before they enquire.'),
                            Repeater::make('items')->label('Questions')->maxItems(8)->defaultItems(0)->reorderable()
                                ->itemLabel(fn (array $state) => $state['question'] ?? null)->collapsible()
                                ->schema([
                                    TextInput::make('question')->required()->maxLength(160),
                                    Textarea::make('answer')->required()->rows(3)->maxLength(600),
                                ]),
                        ]),

                        Tab::make('15. Our offices')->icon(Heroicon::OutlinedMapPin)->statePath('offices')->schema([
                            self::note('Office addresses with directions and phone, after the FAQ.'),
                            Text::make(new HtmlString('The offices themselves are shared by every page and edited under <a href="'.e(OfficeResource::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Website → Offices</a>.'))
                                ->color('gray'),
                            Toggle::make('hidden')->label('Hide the offices on this page'),
                            TextInput::make('heading')->maxLength(120)->placeholder('Our offices'),
                            Textarea::make('intro')->rows(2)->maxLength(200)
                                ->placeholder('Prefer to meet in person? Visit one of our offices, or call and we’ll come to you.'),
                        ]),

                        Tab::make('16. Closing & declaration')->icon(Heroicon::OutlinedMegaphone)->schema([
                            self::note('Blue band with the main button, WhatsApp and phone, then the declaration at the very bottom. Always shown.'),
                            Group::make()->statePath('closing')->schema([
                                TextInput::make('heading')->maxLength(90)->placeholder('Start with a free check'),
                                Textarea::make('text')->rows(2)->maxLength(200),
                            ]),
                            Textarea::make('disclaimer')->label('Declaration')->rows(4)->maxLength(800)
                                ->helperText('Leave empty for the standard text: private consultancy, not a government body, testing by accredited partner labs, government and lab fees at actuals.'),
                        ]),
                    ]),
                ]),

                SeoSection::make(reviewer: false),

                Section::make('Ad & SEO check')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->description('Updates as you edit. Google Ads rates the landing page on how closely it matches the searched words, so repeat the keyword in the headline, the title and the first lines.')
                    ->columnSpanFull()
                    ->schema([Text::make(fn (Get $get) => self::adCheck($get))]),
            ]);
    }

    private static function adCheck(Get $get): HtmlString
    {
        $keyword = trim((string) $get('seo.keyword'));
        $headline = trim((string) $get('content.hero.headline'));
        $subheadline = trim((string) $get('content.hero.subheadline'));
        $title = trim((string) $get('seo.title')) ?: ($headline === '' ? '' : "{$headline} | Instacertify");
        $description = trim((string) $get('seo.description')) ?: $subheadline;

        $checks = [];
        if ($keyword === '') {
            $checks[] = [false, 'Add a primary keyword under SEO & sharing to check the headline, title and description against it.'];
        } else {
            foreach (['Headline (H1)' => $headline, 'Meta title' => $title, 'Text under the headline' => $subheadline, 'Meta description' => $description] as $label => $text) {
                $checks[] = match (self::mentions($keyword, $text)) {
                    'exact' => [true, "{$label} contains “{$keyword}”."],
                    'words' => [true, "{$label} has every word of “{$keyword}”, in a different order."],
                    default => [false, "{$label} doesn’t mention “{$keyword}”."],
                };
            }
        }

        $titleLength = mb_strlen($title);
        $checks[] = [$titleLength > 0 && $titleLength <= 60, "Meta title: {$titleLength} characters".($titleLength > 60 ? ' – Google may cut it after about 60.' : '.')];
        $descriptionLength = mb_strlen($description);
        $checks[] = [$descriptionLength >= 70 && $descriptionLength <= 155, "Meta description: {$descriptionLength} characters".match (true) {
            $descriptionLength > 155 => ' – Google may cut it after about 155.',
            $descriptionLength < 70 => ' – aim for 70 to 155.',
            default => '.',
        }];

        $faqs = count(array_filter((array) $get('content.faqs.items')));
        $checks[] = [$faqs >= 3, $faqs >= 3 ? "{$faqs} FAQs, sent to Google as structured data." : "{$faqs} FAQs – add at least 3 questions buyers ask before they enquire."];

        $photo = filled($get('content.intro.image'));
        $sectionPhotos = collect(['intro', 'benefits', 'checklist'])->filter(fn (string $tab) => filled($get("content.{$tab}.image")))->count();
        $checks[] = [$sectionPhotos >= 2, $sectionPhotos >= 2
            ? 'Photos break up the page further down.'
            : 'Few photos below the top – add them under About the service, Why choose us and Documents.'];
        if ($photo && blank($get('content.intro.image_alt'))) {
            $checks[] = [false, 'The About photo needs a description (alt text).'];
        }
        $checks[] = [filled($get('content.steps.items')), filled($get('content.steps.items')) ? '“How it works” steps are filled in.' : 'Add the steps under How it works – buyers want to know what happens after they enquire.'];

        $items = collect($checks)->map(fn (array $check) => sprintf(
            '<li style="display:flex;gap:.5rem;align-items:flex-start"><span style="font-weight:700;color:%s">%s</span><span>%s</span></li>',
            $check[0] ? '#16a34a' : '#d97706',
            $check[0] ? '✓' : '!',
            e($check[1]),
        ))->implode('');

        $url = 'instacertify.com › '.str_replace('/', ' › ', trim((string) $get('path')) ?: 'lp/…');
        $visibility = $get('indexable') ? '' : '<p style="margin-top:.75rem;font-size:.8125rem;opacity:.75">This page is hidden from Google search, as ad-only pages should be. Google Ads still checks it for the landing page experience part of Quality Score.</p>';

        return new HtmlString(
            '<ul style="display:grid;gap:.5rem;font-size:.875rem">'.$items.'</ul>'
            .'<p style="margin-top:1.25rem;font-size:.8125rem;font-weight:600">Preview in Google search</p>'
            .'<div style="margin-top:.5rem;max-width:600px;padding:.875rem 1rem;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;font-family:Arial,sans-serif">'
            .'<div style="font-size:12px;color:#4d5156">'.e($url).'</div>'
            .'<div style="margin-top:4px;font-size:20px;line-height:1.3;color:#1a0dab">'.e(Str::limit($title ?: 'Meta title', 60)).'</div>'
            .'<div style="margin-top:4px;font-size:14px;line-height:1.58;color:#4d5156">'.e(Str::limit($description ?: 'Meta description', 155)).'</div>'
            .'</div>'.$visibility
        );
    }

    /** Whether the text has the keyword as a phrase ("exact"), has all its words ("words"), or misses it. */
    private static function mentions(string $keyword, string $text): string|false
    {
        $normal = fn (string $value) => ' '.Str::of($value)->lower()->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')->squish().' ';
        $phrase = $normal($keyword);
        $haystack = $normal($text);
        if (trim($phrase) === '' || trim($haystack) === '') {
            return false;
        }
        if (str_contains($haystack, $phrase)) {
            return 'exact';
        }
        $words = array_filter(explode(' ', trim($phrase)), fn (string $word) => mb_strlen($word) > 2);

        return $words && collect($words)->every(fn (string $word) => str_contains($haystack, ' '.$word)) ? 'words' : false;
    }

    /** Where the section sits on the page and when it shows, so editors can match the admin to what they see. */
    private static function note(string $text): Text
    {
        return Text::make($text)->color('gray')->columnSpanFull();
    }

    /** @return array<int, Component> */
    private static function sectionPhoto(string $help): array
    {
        return [
            ImageUpload::make('image', 'landing/sections', maxWidth: 1400)->label('Photo')->imagePreviewHeight('160')->helperText($help),
            TextInput::make('image_alt')->label('Photo description (alt text)')->maxLength(140)->requiredWith('image')
                ->helperText('What the photo shows, for Google Images and screen readers. Required with a photo.'),
        ];
    }

    /** FMCS audits are never shown on landing pages, so they aren't counted. */
    private static function matchingAudits(?array $schemes, ?array $types): string
    {
        if (blank($schemes)) {
            return 'Leave empty to match audits whose title shares this page’s keyword.';
        }
        $titles = Post::published()->where('type', 'audit')->whereNotNull('image')->where('title', 'not like', '%FMCS%')
            ->whereHas('schemes', fn ($q) => $q->whereIn('slug', $schemes))
            ->when(filled($types), fn ($q) => $q->whereIn('work_type', $types))
            ->latest('published_at')->pluck('title');

        return $titles->isEmpty()
            ? 'No published audit has these tags yet, so only uploaded photos show. Tag audits under Resources → Audits & inspections.'
            : $titles->count().' matching '.Str::plural('audit', $titles->count()).', newest first: '.$titles->take(3)->map(fn ($t) => '“'.Str::limit($t, 60).'”')->implode(', ').($titles->count() > 3 ? ' …' : '');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(['title', 'path'])->description(fn (LandingPage $record) => $record->url())->wrap(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('indexable')->label('In Google')->boolean(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()->label('Duplicate')
                    ->beforeReplicaSaved(function (LandingPage $replica) {
                        $replica->title .= ' (copy)';
                        $replica->status = 'draft';
                        $replica->published_at = null;
                        $path = $replica->path.'-copy';
                        while (LandingPage::pathTaken($path)) {
                            $path = $replica->path.'-'.Str::lower(Str::random(4));
                        }
                        $replica->path = $path;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingPages::route('/'),
            'create' => CreateLandingPage::route('/create'),
            'edit' => EditLandingPage::route('/{record}/edit'),
        ];
    }
}
