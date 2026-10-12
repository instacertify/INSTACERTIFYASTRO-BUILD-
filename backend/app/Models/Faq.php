<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question', 'answer', 'category', 'sort', 'is_published'])]
class Faq extends Model
{
    use Audited;

    public const CATEGORIES = [
        'general' => 'General',
        'certification' => 'Certification',
        'testing' => 'Testing',
        'services' => 'Services',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
