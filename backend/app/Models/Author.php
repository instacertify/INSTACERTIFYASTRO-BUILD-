<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legacy_id', 'name', 'slug', 'role', 'company', 'short_bio', 'bio', 'photo', 'experience_years',
    'credentials', 'expertise', 'email', 'links', 'seo', 'is_published',
])]
class Author extends Model
{
    use Audited, HasSeo;

    protected function casts(): array
    {
        return ['expertise' => 'array', 'links' => 'array', 'is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
