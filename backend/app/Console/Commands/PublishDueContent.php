<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Standard;
use App\Models\Vacancy;
use App\Support\SitePublisher;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

#[Signature('site:publish-due')]
#[Description('Publish the website when one of its dates has passed since the last check, and only then')]
class PublishDueContent extends Command
{
    private const CHECKED_AT = 'site:dates-checked-at';

    public function handle(): int
    {
        $now = now();
        $since = Cache::get(self::CHECKED_AT);
        Cache::forever(self::CHECKED_AT, $now->toIso8601String());
        if (! $since) {
            $this->info('First check; dates are compared from now on.');

            return self::SUCCESS;
        }

        $due = self::due(Carbon::parse($since), $now);
        if ($due === []) {
            $this->info('Nothing on the website has changed with the date.');

            return self::SUCCESS;
        }

        SitePublisher::queue();
        $this->info('Publishing: '.implode('; ', $due).'.');

        return self::SUCCESS;
    }

    /**
     * What the website shows differently at $now than at $since.
     *
     * @return list<string>
     */
    public static function due(CarbonInterface $since, CarbonInterface $now): array
    {
        $passed = fn (?CarbonInterface $at): bool => $at !== null && $at->gt($since) && $at->lte($now);
        // The website counts days in UTC: an event or vacancy drops off once its last day is over.
        $dayAfter = fn (?CarbonInterface $date): ?CarbonInterface => $date?->copy()->utc()->startOfDay()->addDay();
        $window = [$since->copy()->subDays(2), $now];
        $due = [];

        foreach ([Post::class => 'articles', Event::class => 'events', Page::class => 'pages', LandingPage::class => 'landing pages', Standard::class => 'standards'] as $model => $label) {
            $count = $model::where('status', 'published')->where('published_at', '>', $since)->where('published_at', '<=', $now)->count();
            if ($count) {
                $due[] = "{$count} scheduled {$label} went live";
            }
        }

        $ended = Event::published()
            ->where(fn ($q) => $q->whereBetween('ends_at', $window)->orWhere(fn ($q) => $q->whereNull('ends_at')->whereBetween('starts_at', $window)))
            ->get(['id', 'starts_at', 'ends_at'])
            ->filter(fn (Event $event) => $passed($dayAfter($event->ends_at ?? $event->starts_at)))
            ->count();
        if ($ended) {
            $due[] = "{$ended} events ended";
        }

        $opened = Vacancy::where('status', 'open')->where('published_at', '>', $since)->where('published_at', '<=', $now)->count();
        if ($opened) {
            $due[] = "{$opened} scheduled vacancies opened";
        }
        $closed = Vacancy::where('status', 'open')->whereBetween('closes_at', $window)->get(['id', 'closes_at'])
            ->filter(fn (Vacancy $vacancy) => $passed($dayAfter($vacancy->closes_at)))
            ->count();
        if ($closed) {
            $due[] = "{$closed} vacancies closed";
        }

        $mandatory = Standard::published()->where('regulation_status', 'upcoming')->whereBetween('mandatory_from', $window)->get(['id', 'mandatory_from'])
            ->filter(fn (Standard $standard) => $passed($standard->mandatory_from?->copy()->utc()->startOfDay()))
            ->count();
        if ($mandatory) {
            $due[] = "{$mandatory} standards became mandatory";
        }

        return $due;
    }
}
