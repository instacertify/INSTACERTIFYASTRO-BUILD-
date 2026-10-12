<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\TracksDiscoverReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'legacy_id', 'title', 'slug', 'type', 'industry_category_id', 'starts_at', 'ends_at', 'location', 'url', 'price',
    'excerpt', 'content', 'image', 'image_alt', 'status', 'published_at', 'seo',
])]
class Event extends Model
{
    use Audited, HasSeo, Publishable, TracksDiscoverReadiness;

    public const TYPES = [
        'conference' => 'Trade fair / conference',
        'seminar' => 'Seminar',
        'webinar' => 'Webinar',
        'workshop' => 'Workshop',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->industry_category_id ??= IndustryCategory::suggest($event->title, $event->excerpt);
        });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('ends_at', '>=', now())
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
    }

    public function industryCategory(): BelongsTo
    {
        return $this->belongsTo(IndustryCategory::class);
    }

    /** Certifications exhibitors at this event usually need. */
    public function schemes(): BelongsToMany
    {
        return $this->belongsToMany(Scheme::class)->orderBy('sort');
    }

    public function url(): string
    {
        return '/resources/events/'.$this->slug;
    }
}
