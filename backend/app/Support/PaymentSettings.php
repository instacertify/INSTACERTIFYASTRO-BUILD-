<?php

namespace App\Support;

use App\Models\Setting;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\RazorpayGateway;
use App\Support\Payments\StripeGateway;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Online payments on the website's /pay page (Settings → Payments), stored as the "payments" setting.
 * The API secrets are stored encrypted and never leave the server; the website only learns whether payments are on.
 */
class PaymentSettings
{
    public const GATEWAYS = ['razorpay' => 'Razorpay', 'stripe' => 'Stripe'];

    /** Two-decimal currencies both gateways accept; amounts are sent in the smallest unit (× 100). */
    public const CURRENCIES = [
        'INR' => 'Indian rupee (₹)',
        'USD' => 'US dollar ($)',
        'EUR' => 'Euro (€)',
        'GBP' => 'Pound sterling (£)',
        'AED' => 'UAE dirham',
        'SGD' => 'Singapore dollar',
        'AUD' => 'Australian dollar',
        'CAD' => 'Canadian dollar',
    ];

    /** The fields holding each gateway's secrets. */
    public const SECRETS = [
        'razorpay' => ['keySecret', 'webhookSecret'],
        'stripe' => ['secretKey', 'webhookSecret'],
    ];

    public const DEFAULTS = [
        'enabled' => false,
        'gateway' => 'razorpay',
        'currency' => 'INR',
        'minAmount' => 100,
        'maxAmount' => 500000,
        'heading' => 'Make a payment',
        'intro' => 'Pay your Instacertify invoice or quotation securely online. Enter your invoice number and the amount exactly as they appear on your invoice.',
        'razorpay' => ['keyId' => null],
        'stripe' => [],
    ];

    /** @return array<string, mixed> settings without the secrets */
    public static function settings(): array
    {
        $saved = Setting::get('payments', []);
        $settings = [...self::DEFAULTS, ...array_filter($saved, fn ($value) => $value !== null && $value !== '')];
        $settings['enabled'] = (bool) ($saved['enabled'] ?? false);
        foreach (self::SECRETS as $gateway => $fields) {
            $settings[$gateway] = [...self::DEFAULTS[$gateway], ...collect($saved[$gateway] ?? [])->except($fields)->all()];
            foreach ($fields as $field) {
                $settings[$gateway][$field] = null;
            }
        }

        return $settings;
    }

    public static function secret(string $gateway, string $field): ?string
    {
        $stored = Setting::get('payments', [])[$gateway][$field] ?? null;
        try {
            return filled($stored) ? Crypt::decryptString($stored) : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public static function hasSecret(string $gateway, string $field): bool
    {
        return filled(self::secret($gateway, $field));
    }

    public static function gateway(): PaymentGateway
    {
        return self::for(self::settings()['gateway']);
    }

    public static function for(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'stripe' => new StripeGateway,
            default => new RazorpayGateway,
        };
    }

    /** Switched on, and the chosen gateway has the keys it needs to take a payment. */
    public static function enabled(): bool
    {
        return self::settings()['enabled'] && self::gateway()->configured();
    }

    /** @return array<string, mixed> what the website shows; never the keys */
    public static function forSite(): array
    {
        $settings = self::settings();
        $enabled = self::enabled();

        return [
            'enabled' => $enabled,
            'gateway' => $enabled ? self::GATEWAYS[$settings['gateway']] : null,
            'testMode' => $enabled && self::gateway()->testMode(),
            'currency' => $settings['currency'],
            'minAmount' => (float) $settings['minAmount'],
            'maxAmount' => (float) $settings['maxAmount'],
            'heading' => $settings['heading'],
            'intro' => $settings['intro'],
        ];
    }
}
