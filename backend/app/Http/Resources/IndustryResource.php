<?php

namespace App\Http\Resources;

use App\Models\Industry;
use App\Models\Service;
use App\Support\Images;
use App\Support\TestCategories;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Industry */
class IndustryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'url' => "/industries/{$this->slug}",
            'name' => $this->name,
            'icon' => $this->icon,
            'iconImage' => Images::url($this->icon_image),
            'image' => Images::url($this->image),
            'summary' => $this->summary,
            'intro' => $this->intro,
            'highlights' => $this->highlights ?? [],
            'certification' => [
                'text' => $this->certification_text,
                'services' => ServiceResource::collection($this->certificationServices()),
            ],
            'testing' => [
                'text' => $this->testing_text,
                'services' => ServiceResource::collection($this->testingServices()),
                'categories' => collect($this->test_categories ?? [])
                    ->filter(fn (string $slug) => isset(TestCategories::ALL[$slug]))
                    ->map(fn (string $slug) => [
                        'slug' => $slug,
                        'label' => TestCategories::ALL[$slug]['label'],
                        'url' => "/services/testing/{$slug}",
                        'count' => Service::published()->where('kind', 'testing')->whereJsonContains('test_categories', $slug)->count(),
                    ])->values(),
            ],
            // No description fallback: the page builds a fuller one from the summary and highlights.
            'seo' => $this->seoMeta("{$this->name} testing & certification"),
            'showClientLogos' => (bool) $this->show_client_logos,
        ];
    }
}
