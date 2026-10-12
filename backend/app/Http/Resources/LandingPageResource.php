<?php

namespace App\Http\Resources;

use App\Models\LandingPage;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LandingPage */
class LandingPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('api.landing-pages.show') || $request->boolean('full');
        $content = $this->content ?? [];
        $seo = $this->seoMeta($content['hero']['headline'] ?? $this->title, $content['hero']['subheadline'] ?? null);
        if (! $this->indexable) {
            $seo['robots'] = 'noindex,follow';
        }

        return [
            'path' => $this->path,
            'url' => $this->url(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'servicePath' => $this->when($full, $this->service_path),
            'content' => $this->when($full, fn () => self::clean(Images::urlsFor($content, ['iconImage', 'image']))),
            'seo' => $this->when($full, $seo),
        ];
    }

    /** Drops empty rows and blank strings so the frontend only checks for presence. */
    private static function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }
        if (! is_array($value)) {
            return $value;
        }
        $out = array_filter(array_map(self::clean(...), $value), fn ($v) => $v !== null && $v !== []);

        return array_is_list($value) ? array_values($out) : $out;
    }
}
