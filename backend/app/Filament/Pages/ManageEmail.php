<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Notifications\TestEmail;
use App\Support\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as Notify;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageEmail extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email & notifications';

    protected static ?string $title = 'Email & notifications';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = MailSettings::settings();
        $this->form->fill([
            ...$settings,
            'leadRecipients' => MailSettings::leadRecipients(),
            'careersRecipients' => MailSettings::careersRecipients(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Status')->compact()->schema([
                Text::make(fn () => MailSettings::status())->weight(FontWeight::SemiBold)
                    ->color(fn () => config('mail.default') === MailSettings::MAILER ? 'success' : 'danger'),
                Text::make(fn () => self::lastDelivery())
                    ->color(fn () => filled(MailSettings::lastDelivery()['error'] ?? null) ? 'danger' : 'gray')
                    ->visible(fn () => MailSettings::lastDelivery() !== null),
            ]),
            Section::make('Who is notified')->columns(2)
                ->description('Emails go out the moment an enquiry or application is submitted.')
                ->schema([
                    self::recipients('leadRecipients', 'New enquiries go to')
                        ->helperText('Every quote, contact and popup enquiry. Hitting Reply in the email answers the customer directly.'),
                    self::recipients('careersRecipients', 'Job applications go to')
                        ->helperText('The hiring team of a vacancy is also notified. CVs are never attached; they stay in the admin.'),
                ]),
            Section::make('Sending account (Gmail or Google Workspace)')->columns(2)
                ->description(new HtmlString('Lead, job application and admin “Forgot password?” emails are sent through a Gmail or Google Workspace mailbox, so they land in the inbox instead of spam. '
                    .'Google needs an <strong>app password</strong> for this, not the normal sign-in password: sign in to the mailbox, turn on '
                    .'<a href="https://myaccount.google.com/signinoptions/twosv" target="_blank" rel="noopener" class="font-semibold text-primary-600 hover:underline">2-Step Verification</a>, '
                    .'then create one at <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="font-semibold text-primary-600 hover:underline">myaccount.google.com/apppasswords</a> '
                    .'(name it "Instacertify website"). Paste the 16 letters below, save, then press <strong>Send test email</strong>.'))
                ->schema([
                    Toggle::make('enabled')->label('Send email through this Google mailbox')->live()->columnSpanFull()
                        ->helperText('When off, the server\'s default mail settings are used.'),
                    TextInput::make('username')->label('Mailbox')->email()->required()->placeholder('contact@instacertify.com'),
                    TextInput::make('password')->label('App password')->password()->revealable()
                        ->autocomplete('new-password')
                        ->placeholder(fn () => MailSettings::hasPassword() ? '•••• •••• •••• •••• (saved — leave empty to keep)' : 'abcd efgh ijkl mnop')
                        ->required(fn (Get $get) => $get('enabled') && ! MailSettings::hasPassword())
                        ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/\s+/', '', $state) : null)
                        ->helperText('Stored encrypted and never shown again.'),
                    TextInput::make('fromAddress')->label('Send as')->email()->placeholder('contact@instacertify.com')
                        ->helperText('The mailbox itself, or an alias added to it under Gmail → Settings → Accounts → "Send mail as". Google replaces any other address.'),
                    TextInput::make('fromName')->label('Sender name')->maxLength(80)->placeholder('Instacertify Website'),
                    TextInput::make('host')->label('SMTP server')->required()->default('smtp.gmail.com')
                        ->helperText('smtp.gmail.com for both Gmail and Google Workspace.'),
                    TextInput::make('port')->label('Port')->required()->integer()->minValue(1)->maxValue(65535)->default(587)
                        ->helperText('587 (STARTTLS, recommended) or 465 (SSL).'),
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
                        Action::make('test')->label('Send test email')->color('gray')->icon(Heroicon::OutlinedPaperAirplane)
                            ->action(fn () => $this->sendTest()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = Setting::firstOrNew(['key' => 'mail']);
        $saved = $setting->value['password'] ?? null;

        $setting->value = [
            ...collect($state)->except('password')->all(),
            'password' => filled($state['password'] ?? null) ? Crypt::encryptString($state['password']) : $saved,
        ];
        $setting->save();
        $this->data['password'] = null;

        MailSettings::apply();

        Notification::make()->success()->title('Email settings saved')
            ->body(MailSettings::status())->send();
    }

    public function sendTest(): void
    {
        $state = $this->form->getState();
        $recipients = $state['leadRecipients'];
        $usesAccount = MailSettings::configure([
            ...MailSettings::DEFAULTS,
            ...$state,
            'password' => ($state['password'] ?? null) ?: MailSettings::password(),
        ]);

        try {
            Notify::route('mail', $recipients)->notifyNow(new TestEmail(auth()->user()->name));

            Notification::make()->success()->persistent()->title('Test email sent')
                ->body('Sent to '.implode(', ', $recipients).($usesAccount ? ' from '.config('mail.from.address').'.' : ' using the server default mail settings ('.config('mail.default').').')
                    .' Check the inbox, and the spam folder if it is not there within a minute.')
                ->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->persistent()->title('The test email could not be sent')
                ->body(MailSettings::explain($e->getMessage()))->send();
        } finally {
            MailSettings::apply();
        }
    }

    private static function recipients(string $name, string $label): TagsInput
    {
        return TagsInput::make($name)->label($label)->required()
            ->placeholder('Add an email and press Enter')->splitKeys(['Enter', ',', ' '])
            ->nestedRecursiveRules(['email:rfc']);
    }

    private static function lastDelivery(): string
    {
        $last = MailSettings::lastDelivery();
        $at = Carbon::parse($last['at'])->timezone('Asia/Kolkata')->format('j M Y, g:i a');

        return filled($last['error'])
            ? "The last lead email FAILED ({$at} IST). Failed emails are retried for about 20 minutes, and every lead is always saved under Leads. ".MailSettings::explain($last['error'])
            : "Last lead email sent {$at} IST.";
    }
}
