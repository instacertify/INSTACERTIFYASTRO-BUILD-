<?php

namespace App\Providers;

use App\Models;
use App\Notifications\ResetAdminPassword;
use App\Policies\AdminPolicy;
use App\Policies\ContentPolicy;
use App\Policies\CrmPolicy;
use App\Policies\JobApplicationPolicy;
use App\Policies\VacancyPolicy;
use App\Support\AuditTrail;
use App\Support\MailSettings;
use App\Support\SitePublisher;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ResetPassword::class, ResetAdminPassword::class);
    }

    public function boot(): void
    {
        // Matches the Team & roles form, so a reset or a password change cannot set a weaker password.
        Password::defaults(fn () => Password::min(12));

        RateLimiter::for('leads', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perDay(30)->by($request->ip()),
        ]);
        RateLimiter::for('consents', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('applications', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(15)->by($request->ip()),
        ]);

        $policies = [
            ContentPolicy::class => [
                Models\Service::class, Models\Industry::class, Models\Testimonial::class, Models\Post::class,
                Models\Category::class, Models\Author::class, Models\Event::class, Models\StandardCategory::class,
                Models\Standard::class, Models\Page::class, Models\Faq::class,
                Models\Redirect::class, Models\Office::class, Models\IndustryCategory::class,
                Models\LandingPage::class, Models\Scheme::class,
            ],
            CrmPolicy::class => [Models\Lead::class, Models\Subscriber::class],
            AdminPolicy::class => [Models\User::class, Models\ConsentLog::class, Activity::class],
            VacancyPolicy::class => [Models\Vacancy::class],
            JobApplicationPolicy::class => [Models\JobApplication::class],
        ];

        foreach ($policies as $policy => $models) {
            foreach ($models as $model) {
                Gate::policy($model, $policy);
            }
        }

        // Listeners must not return false: that stops the listeners after them (e.g. the activity log).
        // A save that changes nothing (e.g. pressing Save twice) leaves the website as it is.
        $changed = fn (Model $model): bool => array_diff(array_keys($model->getChanges()), [$model->getUpdatedAtColumn()]) !== [];
        foreach ([...$policies[ContentPolicy::class], Models\Vacancy::class, Models\SchemeLink::class] as $model) {
            $model::created(fn () => SitePublisher::queue());
            $model::updated(function (Model $record) use ($changed): void {
                if ($changed($record)) {
                    SitePublisher::queue();
                }
            });
            $model::deleted(fn () => SitePublisher::queue());
        }
        // The email account is the only setting the website does not show.
        Models\Setting::created(function (Models\Setting $setting): void {
            if ($setting->key !== 'mail') {
                SitePublisher::queue();
            }
        });
        Models\Setting::updated(function (Models\Setting $setting) use ($changed): void {
            if ($setting->key !== 'mail' && $changed($setting)) {
                SitePublisher::queue();
            }
        });

        AuditTrail::register();

        // The worker outlives settings changes, so re-read the email account before every job.
        $this->app->afterResolving('mail.manager', fn () => MailSettings::apply());
        Queue::before(fn () => MailSettings::apply());
    }
}
