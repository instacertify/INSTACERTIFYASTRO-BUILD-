<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Support\Images;
use App\Support\RichContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('api.services.show') || $request->boolean('detail');

        return [
            'path' => $this->path,
            'url' => $this->url(),
            'slug' => $this->slug,
            'title' => $this->title,
            'kind' => $this->kind,
            'region' => $this->region,
            'group' => $this->group,
            'testCategories' => $this->kind === 'testing' ? array_values($this->test_categories ?? []) : [],
            'summary' => $this->summary,
            'icon' => Images::url($this->icon),
            'featured' => $this->is_featured,
            'hasDetail' => ! empty($this->detail),
            'detail' => $this->when($full, fn () => Images::urlsFor(RichContent::render($this->detail ?: null), ['iconImage'])),
            'seo' => $this->when($full, fn () => $this->seoMeta($this->title, $this->summary)),
            'updatedAt' => $this->when($full, fn () => $this->updated_at?->toIso8601String()),
        ];
    }
}
