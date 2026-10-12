<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Models\Scheme;
use App\Support\Images;
use App\Support\RichContent;
use App\Support\SchemeClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('api.posts.show') || $request->boolean('content');

        return [
            'type' => $this->type,
            'slug' => $this->slug,
            'url' => $this->url(),
            'title' => $this->title,
            'excerpt' => $this->excerpt ?: str($this->content)->stripTags()->squish()->limit(180)->toString(),
            'image' => Images::url($this->image),
            'imageAlt' => $this->image_alt ?: $this->title,
            'imageWidth' => $this->image_width,
            'imageHeight' => $this->image_height,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['name' => $this->category->name, 'slug' => $this->category->slug] : null),
            'industry' => $this->whenLoaded('industryCategory', fn () => $this->industryCategory ? ['name' => $this->industryCategory->name, 'slug' => $this->industryCategory->slug] : null),
            'schemes' => $this->whenLoaded('schemes', fn () => $this->schemes->map(fn (Scheme $s) => ['name' => $s->name, 'slug' => $s->slug])->all()),
            'workType' => $this->work_type,
            'workTypeLabel' => SchemeClassifier::WORK_TYPES[$this->work_type] ?? null,
            'author' => $this->whenLoaded('author', fn () => $this->author ? new AuthorResource($this->author) : null),
            'readingTime' => $this->reading_time,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'content' => $this->when($full, fn () => RichContent::render($this->content)),
            'faqs' => $this->when($full, fn () => $this->faqs ?? []),
            'seo' => $this->when($full, fn () => $this->seoMeta($this->title, $this->excerpt ?: $this->content)),
            'showClientLogos' => $this->when($full, fn () => (bool) $this->show_client_logos),
        ];
    }
}
