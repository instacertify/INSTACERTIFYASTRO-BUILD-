<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'context', 'photo', 'rating', 'quote', 'is_featured', 'is_published', 'sort'])]
class Testimonial extends Model
{
    use Audited;

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_featured' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
