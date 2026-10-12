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
    'legacy_id', 'type', 'work_type', 'title', 'slug', 'excerpt', 'content', 'image', 'image_alt',
    'category_id', 'industry_category_id', 'author_id', 'reading_time', 'faqs', 'status', 'published_at', 'seo', 'show_client_logos',
])]
class Post extends Model
{
    use Audited, HasSeo, Publishable, TracksDiscoverReadiness;

    /** Post type => [label, public URL prefix]. Prefixes match the legacy site's URLs. */
    public const TYPES = [
        'blog' => ['Blog post', 'resources/blogs'],
        'news' => ['News', 'news-item'],
        'audit' => ['Audit & inspection', 'resources/audits'],
        'update' => ['Regulatory update', 'resources/update'],
    ];

    /** Admin section names, matching the website's Resources menu. */
    public const SECTIONS = [
        'blog' => 'Blogs',
        'news' => 'Regulatory news',
        'audit' => 'Audits & inspections',
        'update' => 'Regulatory updates',
    ];

    protected function casts(): array
    {
        return ['faqs' => 'array', 'show_client_logos' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->industry_category_id ??= IndustryCategory::suggest($post->title, $post->excerpt);
        });
        static::saving(function (Post $post) {
            if ($post->isDirty('content') && $post->content) {
                $post->reading_time = max(1, (int) ceil(str_word_count(strip_tags($post->content)) / 220));
            }
        });
    }

    public static function typeOptions(): array
    {
        return array_map(fn (array $t) => $t[0], self::TYPES);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function url(): string
    {
        return '/'.self::TYPES[$this->type][1].'/'.$this->slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function industryCategory(): BelongsTo
    {
        return $this->belongsTo(IndustryCategory::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /** Certifications an audit or case study covers. */
    public function schemes(): BelongsToMany
    {
        return $this->belongsToMany(Scheme::class)->using(SchemeLink::class)->orderBy('sort');
    }
}
