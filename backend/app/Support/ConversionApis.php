<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Server-side conversions: each enquiry is also reported from the server to GA4 (Measurement Protocol), Microsoft
 * Advertising (UET Conversions API) and LinkedIn (Conversions API), so it counts even when the browser tag is blocked.
 * Browser and server send the same event ID, which UET and LinkedIn use to count the enquiry once; GA4 cannot do that,
 * so the browser leaves generate_lead to the server whenever the server can send it.
 * Settings live in the "conversions" setting, apart from the public site settings; tokens are stored encrypted.
 */
class ConversionApis
{
    public const PLATFORMS = ['ga4' => 'Google Analytics 4', 'uet' => 'Microsoft Advertising', 'linkedin' => 'LinkedIn'];

    /** The field holding each platform's secret. */
    public const SECRETS = ['ga4' => 'secret', 'uet' => 'token', 'linkedin' => 'token'];

    /** The field holding each platform's ID, and the pattern it must match. */
    public const IDS = [
        'ga4' => ['measurementId', '/^G-[A-Z0-9]{4,15}$/'],
        'uet' => ['tagId', '/^\d{4,12}$/'],
        'linkedin' => ['conversionId', '/^\d{4,12}$/'],
    ];

    /** Visitor IDs the browser may send with an enquiry; anything else is dropped. */
    public const CONTEXT = [
        'event_id' => '/^[A-Za-z0-9-]{8,64}$/',
        'page_url' => '#^https?://[^\s<>"]{1,490}$#',
        'ga_client_id' => '/^\d{1,20}\.\d{1,20}$/',
        'ga_session_id' => '/^\d{1,20}$/',
        'uet_vid' => '/^[A-Za-z0-9-]{16,64}$/',
        'msclkid' => '/^[A-Za-z0-9_-]{16,80}$/',
        'li_fat_id' => '/^[A-Za-z0-9_-]{8,200}$/',
    ];

    /** @return array<string, array<string, mixed>> settings without the secrets */
    public static function settings(): array
    {
        $saved = Setting::get('conversions', []);

        return collect(self::PLATFORMS)->mapWithKeys(fn ($label, string $platform) => [$platform => [
            'enabled' => (bool) ($saved[$platform]['enabled'] ?? false),
            self::IDS[$platform][0] => $saved[$platform][self::IDS[$platform][0]] ?? null,
            self::SECRETS[$platform] => null,
        ]])->all();
    }

    public static function secret(string $platform): ?string
    {
        $stored = Setting::get('conversions', [])[$platform][self::SECRETS[$platform]] ?? null;
        try {
            return filled($stored) ? Crypt::decryptString($stored) : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public static function hasSecret(string $platform): bool
    {
        return filled(self::secret($platform));
    }

    public static function id(string $platform): ?string
    {
        return self::settings()[$platform][self::IDS[$platform][0]] ?? null;
    }

    /** Switched on, with an ID and a secret. */
    public static function enabled(string $platform): bool
    {
        return self::settings()[$platform]['enabled'] && filled(self::id($platform)) && self::hasSecret($platform);
    }

    /** What the website needs to know; never the secrets. */
    public static function publicFlags(): array
    {
        return [
            'ga4' => self::enabled('ga4') ? self::id('ga4') : null,
            'uet' => self::enabled('uet'),
            'linkedin' => self::enabled('linkedin'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input  the "tracking" object posted with an enquiry
     * @return array<string, string|bool>
     */
    public static function context(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }
        $context = collect(self::CONTEXT)
            ->map(fn (string $pattern, string $key) => is_string($input[$key] ?? null) && preg_match($pattern, $input[$key]) ? $input[$key] : null)
            ->filter()->all();
        $context['ad_consent'] = filter_var($input['ad_consent'] ?? false, FILTER_VALIDATE_BOOL);

        return $context;
    }

    /**
     * Platforms this enquiry goes to: GA4 needs the visitor's Analytics ID (only sent with analytics consent),
     * the ad platforms need marketing consent.
     *
     * @return list<string>
     */
    public static function platformsFor(array $context): array
    {
        return array_values(array_filter(array_keys(self::PLATFORMS), fn (string $platform) => self::enabled($platform) && match ($platform) {
            'ga4' => isset($context['ga_client_id']),
            default => ($context['ad_consent'] ?? false) === true,
        }));
    }

    public static function send(string $platform, Lead $lead, array $context): Response
    {
        return match ($platform) {
            'ga4' => self::sendGa4($lead, $context),
            'uet' => self::sendUet($lead, $context),
            'linkedin' => self::sendLinkedIn($lead, $context),
        };
    }

    private static function sendGa4(Lead $lead, array $context): Response
    {
        return Http::timeout(10)->post('https://www.google-analytics.com/mp/collect?'.http_build_query([
            'measurement_id' => self::id('ga4'),
            'api_secret' => self::secret('ga4'),
        ]), self::ga4Payload($lead, $context));
    }

    public static function ga4Payload(Lead $lead, array $context): array
    {
        $consent = ($context['ad_consent'] ?? false) ? 'GRANTED' : 'DENIED';

        return [
            'client_id' => $context['ga_client_id'],
            'timestamp_micros' => (int) ($lead->created_at->getPreciseTimestamp()),
            'consent' => ['ad_user_data' => $consent, 'ad_personalization' => $consent],
            'events' => [[
                'name' => 'generate_lead',
                'params' => array_filter([
                    'form_name' => $lead->type,
                    'page_location' => $context['page_url'] ?? null,
                    'session_id' => $context['ga_session_id'] ?? null,
                    'engagement_time_msec' => 1,
                ], fn ($value) => $value !== null),
            ]],
        ];
    }

    private static function sendUet(Lead $lead, array $context): Response
    {
        return Http::timeout(10)->withToken(self::secret('uet'))
            ->post('https://capi.uet.microsoft.com/v1/'.self::id('uet').'/events', self::uetPayload($lead, $context));
    }

    public static function uetPayload(Lead $lead, array $context): array
    {
        return ['data' => [array_filter([
            'eventType' => 'custom',
            'eventId' => $context['event_id'] ?? $lead->uuid,
            'eventName' => 'submit_lead_form',
            'eventTime' => $lead->created_at->getTimestamp(),
            'eventSourceUrl' => $context['page_url'] ?? null,
            'adStorageConsent' => 'G',
            'userData' => array_filter([
                'anonymousId' => $context['uet_vid'] ?? null,
                'msclkid' => self::msclkid($context['msclkid'] ?? null),
                'em' => self::hash(self::uetEmail($lead->email)),
                'ph' => self::hash(self::e164($lead->phone)),
                'clientIpAddress' => $lead->ip,
                'clientUserAgent' => $lead->user_agent,
            ]),
            'customData' => ['eventCategory' => 'lead', 'eventLabel' => $lead->type],
        ])]];
    }

    private static function sendLinkedIn(Lead $lead, array $context): Response
    {
        return Http::timeout(10)->withToken(self::secret('linkedin'))
            ->withHeaders(['LinkedIn-Version' => self::linkedInVersion(), 'X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/rest/conversionEvents', self::linkedInPayload($lead, $context));
    }

    public static function linkedInPayload(Lead $lead, array $context): array
    {
        [$first, $last] = array_pad(preg_split('/\s+/', trim((string) $lead->name), 2) ?: [], 2, null);
        $userIds = array_values(array_filter([
            filled($lead->email) ? ['idType' => 'SHA256_EMAIL', 'idValue' => self::hash(strtolower(trim($lead->email)))] : null,
            isset($context['li_fat_id']) ? ['idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID', 'idValue' => $context['li_fat_id']] : null,
        ]));

        return [
            'conversion' => 'urn:lla:llaPartnerConversion:'.self::id('linkedin'),
            'conversionHappenedAt' => (int) $lead->created_at->getTimestampMs(),
            'eventId' => $context['event_id'] ?? $lead->uuid,
            'user' => array_filter([
                'userIds' => $userIds,
                'userInfo' => filled($first) && filled($last)
                    ? array_filter(['firstName' => $first, 'lastName' => $last, 'companyName' => $lead->company])
                    : null,
            ]),
        ];
    }

    /** LinkedIn accepts each monthly API version for about a year; LINKEDIN_API_VERSION pins one if needed. */
    public static function linkedInVersion(): string
    {
        return config('services.linkedin.version') ?: now()->subMonths(2)->format('Ym');
    }

    /**
     * A request the platform must reject as invalid: a 400 proves the token was accepted, a 401/403 that it was not.
     * Nothing is recorded in the ad account. GA4 has no such check; its debug endpoint only validates the event format.
     *
     * @return array{ok: bool, message: string}
     */
    public static function check(string $platform): array
    {
        if (! self::hasSecret($platform) || blank(self::id($platform))) {
            return ['ok' => false, 'message' => 'Fill in the ID and the token or secret first.'];
        }

        try {
            if ($platform === 'ga4') {
                $response = Http::timeout(10)->post('https://www.google-analytics.com/debug/mp/collect?'.http_build_query([
                    'measurement_id' => self::id('ga4'), 'api_secret' => self::secret('ga4'),
                ]), ['client_id' => '123.456', 'events' => [['name' => 'generate_lead', 'params' => ['engagement_time_msec' => 1]]]]);
                $problems = collect($response->json('validationMessages', []))->pluck('description')->filter();

                return $response->successful() && $problems->isEmpty()
                    ? ['ok' => true, 'message' => 'Event format accepted. Google does not confirm API secrets: send a test enquiry and look for generate_lead in Analytics → Reports → Realtime.']
                    : ['ok' => false, 'message' => $problems->implode(' ') ?: "Google answered {$response->status()}."];
            }

            $response = $platform === 'uet'
                ? Http::timeout(10)->withToken(self::secret('uet'))->post('https://capi.uet.microsoft.com/v1/'.self::id('uet').'/events', ['data' => []])
                : Http::timeout(10)->withToken(self::secret('linkedin'))
                    ->withHeaders(['LinkedIn-Version' => self::linkedInVersion(), 'X-Restli-Protocol-Version' => '2.0.0'])
                    ->post('https://api.linkedin.com/rest/conversionEvents', ['conversion' => 'urn:lla:llaPartnerConversion:'.self::id('linkedin')]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach the platform: '.Str::limit($e->getMessage(), 200)];
        }

        return match (true) {
            in_array($response->status(), [401, 403], true) => ['ok' => false, 'message' => "The token was refused ({$response->status()}). Create a new one and paste it again."],
            $response->status() === 404 => ['ok' => false, 'message' => 'The ID was not found (404). Check the tag or conversion ID.'],
            $response->status() < 500 => ['ok' => true, 'message' => 'Token accepted.'],
            default => ['ok' => false, 'message' => "The platform answered {$response->status()}; try again later."],
        };
    }

    public static function recordResult(string $platform, ?string $error = null): void
    {
        Cache::forever("conversions:last:{$platform}", ['at' => now()->toIso8601String(), 'error' => $error]);
    }

    /** @return array{at: string, error: ?string}|null */
    public static function lastResult(string $platform): ?array
    {
        return Cache::get("conversions:last:{$platform}");
    }

    private static function hash(?string $value): ?string
    {
        return filled($value) ? hash('sha256', $value) : null;
    }

    /** Microsoft's normalisation: lowercase, no dots or +alias in the part before the @. */
    private static function uetEmail(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return null;
        }
        [$user, $domain] = explode('@', strtolower(trim($email)), 2);

        return str_replace('.', '', Str::before($user, '+')).'@'.$domain;
    }

    /** E.164; numbers without a country code are taken as Indian. */
    private static function e164(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return match (true) {
            $digits === '' => null,
            str_starts_with((string) $phone, '+') => '+'.$digits,
            strlen($digits) === 10 => '+91'.$digits,
            strlen($digits) === 11 && str_starts_with($digits, '0') => '+91'.substr($digits, 1),
            default => '+'.$digits,
        };
    }

    /** The _uetmsclkid cookie holds "_uet" + 32 hex characters; the API wants a UUID. */
    private static function msclkid(?string $value): ?string
    {
        $hex = strtolower(preg_replace('/^_uet/', '', (string) $value));
        if (! preg_match('/^[0-9a-f]{32}$/', str_replace('-', '', $hex))) {
            return null;
        }
        $hex = str_replace('-', '', $hex);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
