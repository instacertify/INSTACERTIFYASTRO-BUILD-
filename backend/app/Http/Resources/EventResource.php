<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Scheme;
use App\Support\Images;
use App\Support\RichContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('api.events.show') || $request->boolean('full');

        return [
            'slug' => $this->slug,
            'url' => $this->url(),
            'title' => $this->title,
            'type' => $this->type,
            'typeLabel' => Event::TYPES[$this->type] ?? $this->type,
            'startsAt' => $this->starts_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
            'location' => $this->location,
            'industry' => $this->whenLoaded('industryCategory', fn () => $this->industryCategory ? ['name' => $this->industryCategory->name, 'slug' => $this->industryCategory->slug] : null),
            'schemes' => $this->whenLoaded('schemes', fn () => $this->schemes->map(fn (Scheme $s) => ['name' => $s->name, 'slug' => $s->slug])->all()),
            'externalUrl' => $this->url,
            'price' => $this->price,
            'excerpt' => $this->excerpt,
            'image' => Images::url($this->image),
            'imageAlt' => $this->image_alt ?: $this->title,
            'imageWidth' => $this->image_width,
            'imageHeight' => $this->image_height,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'content' => $this->when($full, fn () => RichContent::render($this->content)),
            'seo' => $this->when($full, fn () => $this->seoMeta($this->title, $this->excerpt ?: $this->content)),
            'showClientLogos' => $this->when($full, fn () => (bool) $this->show_client_logos),
        ];
    }
}
