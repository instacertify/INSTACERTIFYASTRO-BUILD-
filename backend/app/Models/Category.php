<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['legacy_id', 'type', 'name', 'slug', 'sort'])]
class Category extends Model
{
    use Audited;

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
