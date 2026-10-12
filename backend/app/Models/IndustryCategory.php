<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\IndustryClassifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** The industry subcategory used inside every resource section (blogs, news, audits, updates, events, standards). */
#[Fillable(['name', 'slug', 'industry_id', 'sort'])]
class IndustryCategory extends Model
{
    use Audited;

    protected static function booted(): void
    {
        static::saving(function (IndustryCategory $category) {
            $category->slug = Str::slug($category->slug ?: $category->name);
        });
        static::updated(function (IndustryCategory $category) {
            if ($category->wasChanged('name')) {
                $category->standards()->update(['sector' => $category->name]);
            }
        });
    }

    public static function idFor(?string $name): ?int
    {
        $name = trim((string) $name);

        return $name === '' ? null : static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
    }

    /** Best-guess subcategory for a new article or event, from its title. */
    public static function suggest(?string $title, ?string $summary = null): ?int
    {
        $name = IndustryClassifier::classify($title, $summary);

        return $name ? static::where('name', $name)->value('id') : null;
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function standards(): HasMany
    {
        return $this->hasMany(Standard::class);
    }
}
