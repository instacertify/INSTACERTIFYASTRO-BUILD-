<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/** Who did what in the admin: settings diffs, sign-ins, exports, plus the IP/browser of every staff action. */
class AuditTrail
{
    public const EVENTS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'exported' => 'Exported',
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'login_failed' => 'Failed sign-in',
        'password_changed' => 'Changed password',
        'cv_viewed' => 'Opened CV',
    ];

    private const MAX_FIELDS = 200;

    public static function register(): void
    {
        Activity::creating(function (Activity $activity) {
            $properties = collect($activity->properties);
            if (auth()->check() && ! $properties->has('ip')) {
                $activity->properties = $properties->merge(self::context());
            }
        });

        Event::listen(Login::class, function (Login $event) {
            activity('auth')->causedBy($event->user)->event('login')->withProperties(self::context())->log('Signed in');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                activity('auth')->causedBy($event->user)->event('logout')->withProperties(self::context())->log('Signed out');
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            activity('auth')
                ->when($event->user instanceof User, fn ($log) => $log->performedOn($event->user))
                ->event('login_failed')
                ->withProperties([...self::context(), 'email' => Str::limit((string) ($event->credentials['email'] ?? ''), 190, '')])
                ->log('Failed sign-in');
        });
    }

    /** @return array{ip: ?string, user_agent: string} */
    public static function context(): array
    {
        return ['ip' => request()->ip(), 'user_agent' => Str::limit((string) request()->userAgent(), 250, '')];
    }

    /** Settings are one JSON row per page, so log only the fields that changed. */
    public static function settingSaved(Setting $setting): void
    {
        $before = $setting->wasRecentlyCreated ? [] : self::dot($setting->getOriginal('value') ?? []);
        $after = self::dot($setting->value ?? []);

        $changed = collect(array_keys($before + $after))
            ->filter(fn (string $key) => ($before[$key] ?? null) != ($after[$key] ?? null))
            ->take(self::MAX_FIELDS);

        if ($changed->isEmpty()) {
            return;
        }

        $page = self::settingLabel($setting->key, $changed->all());
        $show = fn (string $key, mixed $value) => Str::endsWith(strtolower($key), 'password') && filled($value) ? '(hidden)' : $value;

        activity('settings')
            ->event($setting->wasRecentlyCreated ? 'created' : 'updated')
            ->withProperties([
                'page' => $page,
                'setting' => $setting->key,
                'old' => $changed->mapWithKeys(fn ($key) => [$key => $show($key, $before[$key] ?? null)])->all(),
                'attributes' => $changed->mapWithKeys(fn ($key) => [$key => $show($key, $after[$key] ?? null)])->all(),
            ])
            ->log(($setting->wasRecentlyCreated ? 'Created ' : 'Updated ').$page);
    }

    public static function exported(string $name, int $rows): void
    {
        activity('export')->event('exported')->withProperties(['rows' => $rows])->log("Exported {$rows} {$name} to CSV");
    }

    /** "Page", "Blog post", "Site settings" … */
    public static function area(Activity $activity): string
    {
        if ($activity->subject_type) {
            $model = $activity->subject_type;
            $resource = class_exists($model) ? Filament::getModelResource($model) : null;

            return $resource ? Str::ucfirst($resource::getModelLabel()) : Str::headline(class_basename($model));
        }

        return $activity->properties['page']
            ?? ['auth' => 'Sign-in', 'export' => 'CSV export'][$activity->log_name]
            ?? Str::headline($activity->log_name ?: 'System');
    }

    /** The record's title, or what happened for entries without a record. */
    public static function record(Activity $activity): ?string
    {
        if ($activity->subject_type) {
            $subject = $activity->subject;

            return $subject?->title ?? $subject?->name ?? $subject?->email ?? $subject?->from_path
                ?? ($activity->properties['old']['title'] ?? $activity->properties['old']['name'] ?? null)
                ?? '#'.$activity->subject_id.($subject ? '' : ' (deleted)');
        }

        return match ($activity->event) {
            'login_failed' => $activity->properties['email'] ?? null,
            'login', 'logout', 'updated', 'created' => null,
            default => $activity->description,
        };
    }

    /** Admin link to the record that was changed, when it still exists. */
    public static function url(Activity $activity): ?string
    {
        if (! $activity->subject || ! ($resource = Filament::getModelResource($activity->subject))) {
            return null;
        }

        foreach (['edit', 'view'] as $page) {
            if ($resource::hasPage($page) && $resource::canAccess()) {
                return $resource::getUrl($page, ['record' => $activity->subject]);
            }
        }

        return null;
    }

    /** @param  list<string>  $changed */
    private static function settingLabel(string $key, array $changed): string
    {
        return match (true) {
            $key === 'home' => 'Home page',
            $key === 'mail' => 'Email & notifications',
            $key === 'site' && collect($changed)->every(fn ($field) => Str::startsWith($field, 'nav.') || $field === 'nav') => 'Header menu',
            $key === 'site' && collect($changed)->every(fn ($field) => Str::startsWith($field, 'footer.') || $field === 'footer') => 'Footer',
            $key === 'site' => 'Site settings',
            default => Str::headline($key).' settings',
        };
    }

    /** @return array<string, mixed> */
    private static function dot(array $value): array
    {
        return collect(Arr::dot($value))->map(fn ($v) => $v === [] ? null : $v)->all();
    }
}
