<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'legacy_id', 'path', 'template', 'title', 'excerpt', 'content', 'data', 'image',
    'status', 'published_at', 'sort', 'seo',
])]
class Page extends Model
{
    use Audited, HasSeo, Publishable;

    public const TEMPLATES = [
        'default' => 'Standard page',
        'policy' => 'Legal / policy',
        'landing' => 'Ad landing page',
        'hub' => 'Hub (grid of links)',
        'about' => 'About us',
    ];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function url(): string
    {
        return '/'.$this->path;
    }
}
