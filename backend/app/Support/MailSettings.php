<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/** Outgoing email account and notification recipients (Settings → Email & notifications), falling back to .env. */
class MailSettings
{
    public const MAILER = 'workspace';

    private const DELIVERY = 'mail:last-lead-email';

    public const DEFAULTS = [
        'enabled' => false,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'contact@instacertify.com',
        'fromAddress' => 'contact@instacertify.com',
        'fromName' => 'Instacertify Website',
        'leadRecipients' => [],
        'careersRecipients' => [],
    ];

    /** The .env mailer, restored when the saved account is switched off in a long-running worker. */
    private static ?array $original = null;

    /** @return array<string, mixed> Never contains the password; see password(). */
    public static function settings(): array
    {
        $saved = rescue(fn () => Setting::get('mail', []), [], false);

        return array_replace(self::DEFAULTS, array_filter($saved, fn ($v) => filled($v)), ['password' => null]);
    }

    public static function hasPassword(): bool
    {
        return filled(self::password());
    }

    public static function password(): ?string
    {
        $encrypted = rescue(fn () => Setting::get('mail', [])['password'] ?? null, null, false);

        try {
            return $encrypted ? Crypt::decryptString($encrypted) : null;
        } catch (DecryptException) {
            return null;
        }
    }

    /** @return list<string> */
    public static function leadRecipients(): array
    {
        return self::settings()['leadRecipients'] ?: array_values(array_filter([config('instacertify.leads.notify_email')]));
    }

    /** @return list<string> */
    public static function careersRecipients(): array
    {
        return self::settings()['careersRecipients'] ?: array_values(array_filter([config('instacertify.careers.notify_email')]));
    }

    /** Send through the saved account when it is switched on and complete; otherwise keep the .env mailer. */
    public static function apply(): bool
    {
        return self::configure([...self::settings(), 'password' => self::password()]);
    }

    /** @param  array<string, mixed>  $settings  with the plain-text password */
    public static function configure(array $settings): bool
    {
        // Resolving the manager runs apply() once; do that first so it cannot overwrite these settings later.
        $manager = app('mail.manager');
        self::$original ??= ['mail.default' => config('mail.default'), 'mail.from' => config('mail.from')];

        if (! ($settings['enabled'] ?? false) || blank($settings['username'] ?? null) || blank($settings['password'] ?? null)) {
            config(self::$original);

            return false;
        }

        config([
            'mail.mailers.'.self::MAILER => [
                'transport' => 'smtp',
                // 465 is implicit TLS; on 587 the connection is upgraded with STARTTLS.
                'scheme' => (int) $settings['port'] === 465 ? 'smtps' : 'smtp',
                'host' => $settings['host'],
                'port' => (int) $settings['port'],
                'username' => $settings['username'],
                'password' => $settings['password'],
                'timeout' => 20,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
            'mail.default' => self::MAILER,
            'mail.from' => [
                'address' => $settings['fromAddress'] ?: $settings['username'],
                'name' => $settings['fromName'] ?: config('app.name'),
            ],
        ]);

        $manager->purge(self::MAILER);

        return true;
    }

    /** Remembers how the latest lead email went, for the Status box on the settings page. */
    public static function recordDelivery(?Throwable $error = null): void
    {
        rescue(fn () => Cache::forever(self::DELIVERY, [
            'at' => now()->toIso8601String(),
            'error' => $error ? Str::limit($error->getMessage(), 300) : null,
        ]), report: false);
    }

    /** @return array{at: string, error: ?string}|null */
    public static function lastDelivery(): ?array
    {
        return rescue(fn () => Cache::get(self::DELIVERY), null, false);
    }

    /** Plain-English cause of an SMTP error, followed by the error itself. */
    public static function explain(string $error): string
    {
        $hint = match (true) {
            Str::contains($error, ['535', 'Username and Password not accepted', 'BadCredentials']) => 'Google rejected the mailbox or app password. Check the mailbox address, create a new app password and paste it again.',
            Str::contains($error, ['534', 'Application-specific password required']) => 'Google needs an app password for this mailbox, not the normal sign-in password.',
            Str::contains($error, ['Connection could not be established', 'timed out', 'getaddrinfo']) => 'The server could not reach the SMTP server. Check the server name and port, and that the hosting firewall allows outgoing connections on that port.',
            Str::contains($error, ['550', '553', '554']) => 'Google refused the sender or a recipient. "Send as" must be the mailbox itself or one of its aliases.',
            Str::contains($error, ['421', '454', 'Daily user sending limit', 'rate']) => 'Google is limiting how much this mailbox can send right now; it is retried automatically.',
            default => 'See the error below.',
        };

        return $hint.' — '.Str::limit($error, 300);
    }

    /** Human-readable account the site is sending from right now. */
    public static function status(): string
    {
        app('mail.manager');

        if (config('mail.default') === self::MAILER) {
            return 'Sending from '.config('mail.from.address').' through '.config('mail.mailers.'.self::MAILER.'.host').'.';
        }

        if (self::catchesMailLocally()) {
            return 'Not set up: emails go to the built-in test inbox (Mailpit) and are NOT delivered. Switch on the Gmail / Google Workspace account below.';
        }

        return match (config('mail.default')) {
            'log' => 'Not set up: emails are only written to the server log and are NOT delivered. Switch on the Gmail / Google Workspace account below.',
            'array' => 'Not set up: emails are discarded (test mode).',
            default => 'Using the server default mail settings ('.config('mail.default').', '.config('mail.mailers.'.config('mail.default').'.host', 'n/a').').',
        };
    }

    /** Whether email leaves this server for a real mail service (not a log, a test inbox or the void). */
    public static function delivers(): bool
    {
        app('mail.manager');

        return ! in_array(config('mail.default'), ['log', 'array'], true) && ! self::catchesMailLocally();
    }

    /** Why the team may be missing lead emails right now, or null when they are going out. */
    public static function deliveryProblem(): ?string
    {
        if (! self::delivers()) {
            return 'Lead emails are not being delivered: no sending account is set up. Every enquiry is still saved under Leads.';
        }

        $waiting = rescue(fn () => Lead::notEmailed()->count(), 0, false);
        if ($waiting > 0) {
            return $waiting === 1
                ? '1 enquiry has not been emailed to the team for over 30 minutes. It is saved under Leads.'
                : "{$waiting} enquiries have not been emailed to the team for over 30 minutes. They are saved under Leads.";
        }

        return null;
    }

    /** The .env mailer points at a catch-all test inbox on this server rather than a real mail service. */
    private static function catchesMailLocally(): bool
    {
        $default = config('mail.default');

        return $default !== self::MAILER
            && config("mail.mailers.{$default}.transport") === 'smtp'
            && in_array(config("mail.mailers.{$default}.host"), ['mailpit', 'mailhog', 'localhost', '127.0.0.1'], true);
    }
}
