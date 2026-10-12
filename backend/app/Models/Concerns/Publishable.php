<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Draft / published workflow; a published record with a future published_at is "scheduled". */
trait Publishable
{
    public const STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
    ];

    public function initializePublishable(): void
    {
        $this->mergeCasts(['published_at' => 'datetime']);
    }

    protected static function bootPublishable(): void
    {
        static::saving(function ($model) {
            if ($model->status === 'published' && ! $model->published_at) {
                $model->published_at = now();
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->published_at?->isFuture();
    }
}
