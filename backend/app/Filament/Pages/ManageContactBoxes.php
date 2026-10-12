<?php

namespace App\Filament\Pages;

use App\Filament\Components\IconPicker;
use App\Models\Setting;
use App\Support\ContactBoxes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
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
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageContactBoxes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Contact boxes';

    protected static ?string $title = 'Contact boxes';

    protected static ?string $slug = 'contact-boxes';

    protected static ?int $navigationSort = -7;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageContent() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Everything written on and around the enquiry forms. Leave a field empty to use the wording shown in grey. The phone number, email, WhatsApp number and office hours come from Site settings → Contact.';
    }

    public function mount(): void
    {
        $site = Setting::get('site', []);
        $this->form->fill([
            'leadForm' => $site['leadForm'] ?? [],
            'popup' => Arr::only($site['popup'] ?? [], ['title', 'subtitle', 'button']),
            'contactBoxes' => ContactBoxes::forForm(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Enquiry form')->icon('heroicon-o-chat-bubble-left-right')->schema([
                    Text::make('The short form on service, article, standard, event and industry pages, at the foot of most pages and in the popup. The homepage hero and ad landing pages can set their own heading.'),
                    Section::make('Heading and button')->compact()->columns(2)->statePath('leadForm')->schema([
                        TextInput::make('title')->label('Heading')->placeholder('Free Expert Assessment')->maxLength(70),
                        TextInput::make('button')->label('Button text')->placeholder('Get free assessment')->maxLength(40),
                        TextInput::make('subtitle')->label('Line under the heading')->placeholder('Free assessment. We reply within 1 business day.')->maxLength(160)->columnSpanFull(),
                        TextInput::make('privacyNote')->label('Note under the button (optional)')->placeholder('e.g. No spam, ever.')->maxLength(160)->columnSpanFull()
                            ->helperText('Leave empty to show nothing. Data handling is explained in the Privacy Policy linked from the consent tick box.'),
                    ]),
                    Section::make('Fields')->compact()->collapsed()->schema([
                        Grid::make(2)->statePath('leadForm')->schema([
                            TextInput::make('nameLabel')->label('Name label')->placeholder('Name')->maxLength(40),
                            TextInput::make('phoneLabel')->label('Phone label')->placeholder('Phone / WhatsApp')->maxLength(40),
                            TextInput::make('emailLabel')->label('Email label')->placeholder('Email')->maxLength(40),
                            TextInput::make('productLabel')->label('Product label')->placeholder('Product or requirement')->maxLength(40),
                            TextInput::make('productPlaceholder')->label('Example in the product field')->placeholder('e.g. Bluetooth speaker, BIS + WPC')->maxLength(80)->columnSpanFull(),
                        ]),
                        Grid::make(3)->statePath('contactBoxes.form')->schema([
                            self::text('form', 'namePlaceholder', 'Example in the name field', 60),
                            self::text('form', 'phonePlaceholder', 'Example in the phone field', 40),
                            self::text('form', 'emailPlaceholder', 'Example in the email field', 60),
                        ]),
                    ]),
                    Section::make('Tick boxes')->compact()->statePath('contactBoxes.form')->schema([
                        self::consent('form'),
                        self::text('form', 'marketing', 'Newsletter tick box', 160),
                    ]),
                    Section::make('After sending')->compact()->collapsed()->schema([
                        Grid::make(1)->statePath('leadForm')->schema([
                            TextInput::make('successTitle')->label('Thank-you heading')->placeholder('Thank you — we\'ve got it.')->maxLength(70),
                            Textarea::make('successText')->label('Thank-you message')->rows(2)->placeholder('A certification specialist will contact you within 1 business day, usually much sooner.')->maxLength(300),
                        ]),
                        Grid::make(1)->statePath('contactBoxes.form')->schema([
                            self::text('form', 'successWhatsapp', 'WhatsApp button under the thank-you message', 60),
                        ]),
                    ]),
                ]),

                Tab::make('Contact page')->icon('heroicon-o-envelope')->schema([
                    Text::make('The /contact page: the text beside the two-step form, the form itself, the contact tiles under it and the offices.'),
                    Section::make('Beside the form')->compact()->statePath('contactBoxes.contactPage')->schema([
                        self::text('contactPage', 'heading', 'Page heading', 90),
                        self::area('contactPage', 'intro', 'Introduction', 400),
                        self::list('points', 'Ticked points', 'Add point', 6),
                    ]),
                    Section::make('Form')->compact()->collapsed()->schema([
                        Grid::make(2)->statePath('leadForm')->schema([
                            TextInput::make('contactTitle')->label('Heading')->placeholder('Same as the Enquiry form tab')->maxLength(70),
                            TextInput::make('contactButton')->label('Send button')->placeholder('Get my free quote')->maxLength(40),
                            TextInput::make('contactSubtitle')->label('Line under the heading')->placeholder('Takes under a minute. Two quick steps.')->maxLength(160)->columnSpanFull(),
                        ]),
                        Grid::make(2)->statePath('contactBoxes.contactPage')->schema([
                            self::text('contactPage', 'stepOne', 'Step 1 name', 40),
                            self::text('contactPage', 'stepTwo', 'Step 2 name', 40),
                            self::text('contactPage', 'requirementLabel', '"What do you need?" label', 60),
                            self::text('contactPage', 'continue', 'Continue button', 40),
                            self::text('contactPage', 'manufacturerLabel', '"You are a" label', 40),
                            self::text('contactPage', 'timelineLabel', 'Timeline label', 40),
                            self::text('contactPage', 'companyLabel', 'Company label', 40),
                            self::text('contactPage', 'back', 'Back button', 30),
                            self::text('contactPage', 'messageLabel', 'Message label', 60),
                            self::text('contactPage', 'messagePlaceholder', 'Example in the message field', 80),
                            self::consent('contactPage')->columnSpanFull(),
                            self::text('contactPage', 'marketing', 'Newsletter tick box', 160)->columnSpanFull(),
                        ]),
                        Text::make('Name, phone, email and product labels are shared with the Enquiry form tab.'),
                    ]),
                    Section::make('Contact tiles under the form')->compact()->collapsed()->columns(2)->statePath('contactBoxes.contactPage')->schema([
                        self::text('contactPage', 'callLabel', 'Phone tile', 40),
                        self::text('contactPage', 'emailLabel', 'Email tile', 40),
                        self::text('contactPage', 'whatsappLabel', 'WhatsApp tile', 40),
                        self::text('contactPage', 'whatsappText', 'WhatsApp tile text', 60),
                        self::text('contactPage', 'hoursLabel', 'Office hours tile', 40),
                    ]),
                    Section::make('Offices')->compact()->collapsed()->statePath('contactBoxes.contactPage')->schema([
                        self::text('contactPage', 'officesHeading', 'Heading', 60),
                        self::area('contactPage', 'officesIntro', 'Introduction', 300)
                            ->helperText('{offices} is replaced with the office names, e.g. "Noida, Mumbai and Chennai".'),
                    ]),
                    Section::make('Search engines')->compact()->collapsed()->statePath('contactBoxes.contactPage')->schema([
                        self::text('contactPage', 'seoTitle', 'Page title', 70),
                        self::area('contactPage', 'seoDescription', 'Meta description', 170),
                    ]),
                ]),

                Tab::make('Bottom of page')->icon('heroicon-o-arrow-down-on-square')->statePath('contactBoxes.cta')->schema([
                    Text::make('The blue box with the enquiry form at the foot of most pages. The Industries and Product testing pages use their own heading and text.'),
                    self::text('cta', 'heading', 'Heading', 90),
                    self::area('cta', 'text', 'Text', 400),
                    self::list('points', 'Ticked points', 'Add point', 5),
                    Grid::make(2)->schema([
                        self::text('cta', 'whatsapp', 'WhatsApp link', 40),
                        self::text('cta', 'button', 'Button on phones', 40)
                            ->helperText('Service pages only: on phones it jumps to the form at the top instead of showing a second form.'),
                    ]),
                ]),

                Tab::make('Popup')->icon('heroicon-o-window')->schema([
                    Text::make(new HtmlString('The enquiry popup opened by the "Get a free quote" buttons. Whether it also opens by itself, its delay and its side image are set in <a href="'.e(ManageSite::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Site settings → Popup</a>.')),
                    Grid::make(2)->statePath('popup')->schema([
                        TextInput::make('title')->label('Heading')->placeholder('Same as the Enquiry form tab')->maxLength(70),
                        TextInput::make('button')->label('Button text')->placeholder('Same as the Enquiry form tab')->maxLength(40),
                        TextInput::make('subtitle')->label('Line under the heading')->placeholder('Same as the Enquiry form tab')->maxLength(160)->columnSpanFull(),
                    ]),
                    Grid::make(2)->statePath('contactBoxes.popup')->schema([
                        self::text('popup', 'talk', 'Line under the form', 60)->helperText('Followed by the phone number and a WhatsApp link.'),
                        self::text('popup', 'whatsapp', 'WhatsApp link', 30),
                    ]),
                ]),

                Tab::make('Service page sidebar')->icon('heroicon-o-phone')->columns(3)->statePath('contactBoxes.expert')->schema([
                    Text::make('The small box under the enquiry form beside service pages. The office hours are shown under the heading.')->columnSpanFull(),
                    self::text('expert', 'heading', 'Heading', 50),
                    self::text('expert', 'call', 'Call button', 20),
                    self::text('expert', 'chat', 'WhatsApp button', 20),
                ]),

                Tab::make('BIS fee finder')->icon('heroicon-o-calculator')->columns(2)->statePath('contactBoxes.feeFinder')->schema([
                    Text::make('The form a visitor fills in to see the BIS fees, on ad landing pages with the fee finder. Its heading and introduction are set on each landing page.')->columnSpanFull(),
                    self::area('feeFinder', 'gateText', 'Text above the form', 300)->columnSpanFull(),
                    self::text('feeFinder', 'turnoverHint', 'Note under the business sizes', 120)->columnSpanFull(),
                    self::text('feeFinder', 'button', 'Button text', 40),
                    self::text('feeFinder', 'whatsapp', 'WhatsApp button with the fees', 40),
                    self::text('feeFinder', 'thanks', 'Thank-you line above the fees', 160)->columnSpanFull(),
                    self::text('feeFinder', 'disclaimer', 'Note under the fees', 200)->columnSpanFull(),
                    self::text('feeFinder', 'noMatch', 'When no product matches', 160),
                    self::text('feeFinder', 'ask', 'Link to the enquiry form', 60),
                ]),

                Tab::make('Footer')->icon('heroicon-o-queue-list')->schema([
                    Text::make(new HtmlString('The newsletter heading, its short text and switching it off are in <a href="'.e(ManageFooter::getUrl()).'" class="font-semibold text-primary-600 hover:underline">Website → Footer</a>.')),
                    Section::make('Newsletter sign-up')->compact()->columns(2)->statePath('contactBoxes.newsletter')->schema([
                        self::text('newsletter', 'placeholder', 'Example in the email field', 60),
                        self::text('newsletter', 'button', 'Button text', 30),
                        self::text('newsletter', 'note', 'Note under the field', 160)->columnSpanFull()->helperText('Followed by a link to the Privacy Policy.'),
                    ]),
                    Section::make('Office location')->compact()->columns(2)->statePath('contactBoxes.footerLocation')->schema([
                        self::text('footerLocation', 'text', 'Text', 160)->columnSpanFull(),
                        self::text('footerLocation', 'button', 'Button text', 30),
                    ]),
                ]),

                Tab::make('Thank-you page')->icon('heroicon-o-check-circle')->statePath('contactBoxes.thankYou')->schema([
                    Text::make('The page visitors see after sending the form on the contact page and on ad landing pages.'),
                    Section::make('Top of the page')->compact()->schema([
                        self::text('thankYou', 'heading', 'Heading', 90),
                        self::text('thankYou', 'text', 'Text', 200)->helperText('The office hours are added in brackets.'),
                        self::text('thankYou', 'whatsapp', 'WhatsApp button', 60),
                    ]),
                    Section::make('What happens next')->compact()->collapsed()->schema([
                        self::text('thankYou', 'stepsHeading', 'Heading', 60),
                        Repeater::make('steps')->hiddenLabel()
                            ->schema([
                                TextInput::make('title')->required()->maxLength(60),
                                IconPicker::make(),
                                Textarea::make('text')->rows(2)->maxLength(200)->columnSpanFull(),
                            ])
                            ->columns(2)->reorderableWithButtons()->maxItems(5)->defaultItems(0)->addActionLabel('Add step')
                            ->itemLabel(fn (array $state) => $state['title'] ?? null),
                    ]),
                    Section::make('Keep these handy')->compact()->collapsed()->schema([
                        self::text('thankYou', 'handyHeading', 'Heading', 60),
                        self::text('thankYou', 'handyIntro', 'Text', 200),
                        self::list('handy', 'Things to keep handy', 'Add item', 8),
                        self::text('thankYou', 'emailPrompt', 'Line before the email address', 60),
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
                        Action::make('save')->label('Save contact boxes')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $boxes = $state['contactBoxes'] ?? [];
        foreach (ContactBoxes::defaults() as $box => $fields) {
            foreach ($fields as $key => $default) {
                if (is_array($default) && array_is_list($default) && is_array($boxes[$box][$key] ?? null)) {
                    $boxes[$box][$key] = array_values($boxes[$box][$key]);
                }
            }
        }

        $setting = Setting::firstOrNew(['key' => 'site']);
        $site = $setting->value ?? [];
        $setting->value = [
            ...$site,
            'leadForm' => [...($site['leadForm'] ?? []), ...($state['leadForm'] ?? [])],
            'popup' => [...($site['popup'] ?? []), ...($state['popup'] ?? [])],
            'contactBoxes' => $boxes,
        ];
        $setting->save();

        Notification::make()->success()->title('Contact boxes saved')->body('The website starts updating in a few seconds.')->send();
    }

    private static function text(string $box, string $key, string $label, int $max): TextInput
    {
        return TextInput::make($key)->label($label)->placeholder(ContactBoxes::default($box, $key))->maxLength($max);
    }

    private static function area(string $box, string $key, string $label, int $max): Textarea
    {
        return Textarea::make($key)->label($label)->rows(2)->placeholder(ContactBoxes::default($box, $key))->maxLength($max);
    }

    private static function list(string $key, string $label, string $add, int $max): Repeater
    {
        return Repeater::make($key)->label($label)
            ->simple(TextInput::make('text')->hiddenLabel()->required()->maxLength(120))
            ->reorderableWithButtons()->maxItems($max)->defaultItems(0)->addActionLabel($add);
    }

    private static function consent(string $box): TextInput
    {
        return self::text($box, 'consent', 'Consent tick box', 300)
            ->helperText('Required before sending. The words "Privacy Policy" become a link to the policy.')
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && stripos((string) $value, 'privacy policy') === false) {
                    $fail('Keep the words "Privacy Policy" in this text, so visitors can open the policy before they agree.');
                }
            });
    }
}
