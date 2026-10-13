<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\PaymentSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManagePayments extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $title = 'Online payments';

    protected ?string $subheading = 'The website\'s /pay page, where clients pay an invoice by card, UPI or net banking. Pick a gateway, paste its API keys and switch payments on. A "Make a payment" button appears in the website footer while payments are on.';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(PaymentSettings::settings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Payment page')->columns(2)->schema([
                Toggle::make('enabled')->label('Take payments on the website')->live()->columnSpanFull()
                    ->helperText('Off: the payment page asks clients to contact you instead, and the footer button is hidden.'),
                ToggleButtons::make('gateway')->label('Payment gateway')->options(PaymentSettings::GATEWAYS)->inline()->required()->live(),
                Select::make('currency')->options(PaymentSettings::CURRENCIES)->required()->native(false)
                    ->helperText('Must be enabled on your gateway account. Razorpay and Stripe India take INR by default; other currencies need international payments switched on.'),
                TextInput::make('minAmount')->label('Smallest amount')->numeric()->minValue(1)->required(),
                TextInput::make('maxAmount')->label('Largest amount')->numeric()->required()->gt('minAmount')
                    ->helperText('Larger payments are asked to use bank transfer.'),
                TextInput::make('heading')->label('Page heading')->required()->maxLength(80)->columnSpanFull(),
                Textarea::make('intro')->label('Introduction')->rows(2)->required()->maxLength(400)->columnSpanFull(),
            ]),

            Section::make('Razorpay')->statePath('razorpay')->columns(2)->compact()
                ->description('Used when Razorpay is the selected gateway. Keys: Razorpay Dashboard → Account & Settings → API keys. Test keys (rzp_test_…) take no real money.')
                ->schema([
                    TextInput::make('keyId')->label('Key ID')->placeholder('rzp_live_XXXXXXXXXXXXXX')->maxLength(40)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                        ->regex('/^rzp_(test|live)_[A-Za-z0-9]+$/')
                        ->validationMessages(['regex' => 'It should start with rzp_test_ or rzp_live_.'])
                        ->required(fn (Get $get) => $get('../enabled') && $get('../gateway') === 'razorpay'),
                    self::secret('razorpay', 'keySecret', 'Key secret', fn (Get $get) => $get('../enabled') && $get('../gateway') === 'razorpay'),
                    self::secret('razorpay', 'webhookSecret', 'Webhook secret'),
                    Text::make(fn () => self::webhookHelp('razorpay', 'Razorpay Dashboard → Account & Settings → Webhooks → Add new webhook', 'payment.captured, payment.failed and order.paid'))->columnSpanFull(),
                ]),

            Section::make('Stripe')->statePath('stripe')->columns(2)->compact()
                ->description('Used when Stripe is the selected gateway. Keys: Stripe Dashboard → Developers → API keys. Test keys (sk_test_…) take no real money.')
                ->schema([
                    self::secret('stripe', 'secretKey', 'Secret key', fn (Get $get) => $get('../enabled') && $get('../gateway') === 'stripe')
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                            if (filled($value) && ! preg_match('/^(sk|rk)_(test|live)_/', trim((string) $value))) {
                                $fail('It should start with sk_live_, sk_test_ or rk_ (restricted key).');
                            }
                        }),
                    self::secret('stripe', 'webhookSecret', 'Webhook signing secret'),
                    Text::make(fn () => self::webhookHelp('stripe', 'Stripe Dashboard → Developers → Webhooks → Add endpoint', 'checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed and checkout.session.expired'))->columnSpanFull(),
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
                        Action::make('check')->label('Check keys')->color('gray')->icon(Heroicon::OutlinedSignal)
                            ->action(fn () => $this->check()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = Setting::firstOrNew(['key' => 'payments']);
        $stored = $setting->value ?? [];

        $value = collect($state)->except(array_keys(PaymentSettings::SECRETS))->all();
        foreach (PaymentSettings::SECRETS as $gateway => $fields) {
            $value[$gateway] = collect($state[$gateway] ?? [])->except($fields)->all();
            foreach ($fields as $field) {
                $new = $state[$gateway][$field] ?? null;
                $value[$gateway][$field] = filled($new) ? Crypt::encryptString($new) : ($stored[$gateway][$field] ?? null);
                $this->data[$gateway][$field] = null;
            }
        }
        $setting->value = $value;
        $setting->save();

        $notification = Notification::make()->title('Payment settings saved')->body('The website is republished with the change.');
        if ($value['enabled'] && ! PaymentSettings::enabled()) {
            $notification->warning()->body('Payments stay off until the '.PaymentSettings::GATEWAYS[$value['gateway']].' keys are filled in.');
        } else {
            $notification->success();
        }
        $notification->send();
    }

    public function check(): void
    {
        $gateway = PaymentSettings::settings()['gateway'];
        try {
            $result = PaymentSettings::for($gateway)->check();
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'Could not reach the gateway: '.Str::limit($e->getMessage(), 200)];
        }

        Notification::make()->persistent()
            ->title(PaymentSettings::GATEWAYS[$gateway].' connection')
            ->status($result['ok'] ? 'success' : 'danger')
            ->body(($result['ok'] ? '✓ ' : '✗ ').$result['message'].' Save first if you just changed the keys.')
            ->send();
    }

    private static function secret(string $gateway, string $field, string $label, ?\Closure $required = null): TextInput
    {
        return TextInput::make($field)->label($label)->password()->revealable()->autocomplete('new-password')->maxLength(500)
            ->placeholder(fn () => PaymentSettings::hasSecret($gateway, $field) ? '•••••••• (saved — leave empty to keep)' : null)
            ->required(fn (Get $get) => $required && $required($get) && ! PaymentSettings::hasSecret($gateway, $field))
            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
            ->helperText('Stored encrypted and never shown again.');
    }

    private static function webhookHelp(string $gateway, string $where, string $events): HtmlString
    {
        $url = e(route('api.payments.webhook', $gateway));

        return new HtmlString("<strong>Webhook (recommended):</strong> {$where}, enter <code>{$url}</code> as the URL, choose the events {$events}, then paste the secret above. It records payments even when the client closes the browser before returning to the site.");
    }
}
