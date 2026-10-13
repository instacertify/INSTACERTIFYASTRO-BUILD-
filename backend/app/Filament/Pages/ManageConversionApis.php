<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\ConversionApis;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageConversionApis extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Conversion APIs';

    protected static ?string $title = 'Conversion APIs';

    protected ?string $subheading = 'Optional. Each enquiry is also reported from the server, so it still counts when a visitor\'s browser blocks tracking tags. Only sent for visitors who allowed the matching cookies.';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = ConversionApis::settings();
        $site = Setting::get('site', [])['integrations'] ?? [];
        $settings['ga4']['measurementId'] ??= $site['googleTag'] ?? null;
        $settings['uet']['tagId'] ??= $site['microsoftAds'] ?? null;
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            self::platform('ga4', 'Google Analytics 4 — Measurement Protocol', 'Measurement ID', 'G-XXXXXXXXXX', 'API secret',
                'Analytics → Admin → Data streams → your web stream → the Measurement ID (G-…), the same one Tag Manager uses.',
                'Same page → Measurement Protocol API secrets → Create. Sends generate_lead from the server; the browser then leaves it out, so it is counted once.'),
            self::platform('uet', 'Microsoft Advertising — UET Conversions API', 'UET tag ID', '123456789', 'API token',
                'Microsoft Advertising → Tools → UET tag → the tag ID (the same as under Site settings → Tracking).',
                'Same tag → Conversions API → generate a token. Sends submit_lead_form with the browser\'s event ID, so Microsoft counts each enquiry once.'),
            self::platform('linkedin', 'LinkedIn — Conversions API', 'Conversion ID (Conversions API rule)', '12345678', 'Access token',
                'Campaign Manager → Analyze → Conversion tracking → create a conversion whose data source is "Conversions API" (separate from the Insight Tag one) → the number after /conversions/ in its address.',
                'Campaign Manager → Data → Data integrations → Conversions API → generate an access token. LinkedIn counts each enquiry once using the browser\'s event ID. Tokens expire after a year; a refused token shows below.'),
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
                        Action::make('check')->label('Check connections')->color('gray')->icon(Heroicon::OutlinedSignal)
                            ->action(fn () => $this->check()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = Setting::firstOrNew(['key' => 'conversions']);
        $stored = $setting->value ?? [];

        $setting->value = collect(ConversionApis::PLATFORMS)->mapWithKeys(function ($label, string $platform) use ($state, $stored) {
            $field = ConversionApis::SECRETS[$platform];
            $new = $state[$platform][$field] ?? null;

            return [$platform => [
                ...collect($state[$platform] ?? [])->except($field)->all(),
                $field => filled($new) ? Crypt::encryptString($new) : ($stored[$platform][$field] ?? null),
            ]];
        })->all();
        $setting->save();
        foreach (ConversionApis::SECRETS as $platform => $field) {
            $this->data[$platform][$field] = null;
        }

        Notification::make()->success()->title('Conversion API settings saved')
            ->body('The website is republished so the browser tags pair up with the server.')->send();
    }

    public function check(): void
    {
        $results = collect(ConversionApis::PLATFORMS)
            ->filter(fn ($label, string $platform) => ConversionApis::settings()[$platform]['enabled'])
            ->map(fn (string $label, string $platform) => [$label, ConversionApis::check($platform)]);

        if ($results->isEmpty()) {
            Notification::make()->warning()->title('Nothing to check')->body('Switch a platform on and save first.')->send();

            return;
        }
        Notification::make()->persistent()
            ->title('Connection check')
            ->status($results->every(fn ($result) => $result[1]['ok']) ? 'success' : 'danger')
            ->body(new HtmlString($results->map(fn ($result) => '<p><strong>'.e($result[0]).':</strong> '.($result[1]['ok'] ? '✓ ' : '✗ ').e($result[1]['message']).'</p>')->implode('')))
            ->send();
    }

    private static function platform(string $platform, string $title, string $idLabel, string $example, string $secretLabel, string $idHelp, string $secretHelp): Section
    {
        [$idField, $pattern] = ConversionApis::IDS[$platform];
        $secretField = ConversionApis::SECRETS[$platform];

        return Section::make($title)->statePath($platform)->columns(2)->compact()->schema([
            Toggle::make('enabled')->label('Report enquiries to '.ConversionApis::PLATFORMS[$platform].' from the server')->live()->columnSpanFull(),
            TextInput::make($idField)->label($idLabel)->placeholder($example)->maxLength(20)
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                ->required(fn (Get $get) => $get('enabled'))
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($pattern, $example) {
                    if (filled($value) && ! preg_match($pattern, strtoupper(trim((string) $value)))) {
                        $fail("It should look like {$example}.");
                    }
                })
                ->helperText($idHelp),
            TextInput::make($secretField)->label($secretLabel)->password()->revealable()->autocomplete('new-password')->maxLength(2000)
                ->placeholder(fn () => ConversionApis::hasSecret($platform) ? '•••••••• (saved — leave empty to keep)' : null)
                ->required(fn (Get $get) => $get('enabled') && ! ConversionApis::hasSecret($platform))
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                ->helperText($secretHelp.' Stored encrypted and never shown again.'),
            Text::make(fn () => self::lastResult($platform))
                ->color(fn () => filled(ConversionApis::lastResult($platform)['error'] ?? null) ? 'danger' : 'gray')
                ->visible(fn () => ConversionApis::lastResult($platform) !== null)
                ->columnSpanFull(),
        ]);
    }

    private static function lastResult(string $platform): string
    {
        $last = ConversionApis::lastResult($platform);
        $at = Carbon::parse($last['at'])->timezone('Asia/Kolkata')->format('j M Y, g:i a');

        return filled($last['error'])
            ? "Last enquiry NOT accepted ({$at} IST): {$last['error']}"
            : "Last enquiry reported {$at} IST.";
    }
}
