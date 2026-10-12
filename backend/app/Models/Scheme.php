<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\SchemeClassifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** A certification, approval or service (BIS ISI, EPR, MSDS…) that audits and events are tagged with, so lists and landing pages can filter by it. */
#[Fillable(['name', 'slug', 'sort'])]
class Scheme extends Model
{
    use Audited;

    protected static function booted(): void
    {
        static::saving(function (Scheme $scheme) {
            $scheme->slug = Str::slug($scheme->slug ?: $scheme->name);
        });
    }

    /** @return list<int> */
    public static function suggest(?string $title, ?string $summary = null, ?string $industry = null): array
    {
        $slugs = SchemeClassifier::classify($title, $summary, $industry);

        return $slugs ? static::whereIn('slug', $slugs)->orderBy('sort')->pluck('id')->all() : [];
    }

    /** @return array<string, string> slug => name */
    public static function options(): array
    {
        return static::orderBy('sort')->pluck('name', 'slug')->all();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }
}
