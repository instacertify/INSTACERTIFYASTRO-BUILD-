<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['legacy_id', 'title', 'slug', 'group', 'description', 'image', 'seo', 'is_published', 'sort'])]
class StandardCategory extends Model
{
    use Audited, HasSeo;

    /** Tiles on the standards library page; categories are grouped under these. */
    public const GROUPS = [
        'mandatory' => 'Mandatory standards',
        'voluntary' => 'Voluntary standards',
        'bee' => 'BEE star labelling',
        'management' => 'Management systems',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function standards(): HasMany
    {
        return $this->hasMany(Standard::class)->orderBy('sort')->orderBy('title');
    }

    public function url(): string
    {
        return '/resources/standards/'.$this->slug;
    }
}
