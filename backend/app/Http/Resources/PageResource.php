<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Support\Images;
use App\Support\RichContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Page */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('api.pages.show') || $request->boolean('full');

        return [
            'path' => $this->path,
            'url' => $this->url(),
            'template' => $this->template,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'image' => Images::url($this->image),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'content' => $this->when($full, fn () => RichContent::render($this->content)),
            'blocks' => $this->when($full, fn () => Images::urlsFor($this->data, ['iconImage', 'quality_image'])),
            'seo' => $this->when($full, fn () => $this->seoMeta($this->title, $this->excerpt ?: $this->content)),
            'showClientLogos' => $this->when($full, fn () => (bool) $this->show_client_logos),
        ];
    }
}
