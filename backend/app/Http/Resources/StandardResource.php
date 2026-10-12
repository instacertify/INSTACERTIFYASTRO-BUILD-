<?php

namespace App\Http\Resources;

use App\Models\Standard;
use App\Support\Images;
use App\Support\RichContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Standard */
class StandardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('api.standards.show') || $request->boolean('detail');
        $status = $this->currentStatus();
        $products = collect($this->products ?? [])
            ->map(fn (array $row) => isset($row['status'])
                ? ['status' => Standard::inForce($row['status'], $row['mandatory_from'] ?? null)] + $row
                : $row)
            ->map(fn (array $row) => filled($row['image'] ?? null) ? ['image' => Images::url($row['image'])] + $row : $row);
        $fees = $products->flatMap(fn ($row) => [$row['fee_micro'] ?? null, $row['fee_large'] ?? null])->filter();

        return [
            'path' => $this->path,
            'url' => $this->url(),
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->whenLoaded('category', fn () => ['title' => $this->category->title, 'slug' => $this->category->slug, 'url' => $this->category->url()]),
            'summary' => str($this->description)->stripTags()->squish()->limit(180)->toString(),
            'scheme' => $this->scheme,
            'regulationStatus' => $status,
            'statusLabel' => $status ? Standard::REGULATION[$status] : null,
            'mandatorySince' => $this->mandatory_since,
            'mandatoryFrom' => $this->mandatory_from?->toDateString(),
            'sector' => $this->sector,
            'isNumbers' => $products->pluck('is_number')->filter()->unique()->take(6)->values(),
            'isKeys' => array_values(array_filter(explode(' ', (string) $this->is_keys))),
            'productCount' => $products->count(),
            'feeMin' => $fees->min(),
            'feeMax' => $fees->max(),
            'testMin' => $products->pluck('test_min')->filter()->min(),
            'description' => $this->when($full, fn () => RichContent::render($this->description)),
            'productsTitle' => $this->when($full, fn () => $this->products_title),
            'products' => $this->when($full, fn () => $products->all()),
            'facts' => $this->when($full, fn () => $this->facts ?? []),
            'faqs' => $this->when($full, fn () => $this->faqs ?? []),
            'image' => $this->when($full, fn () => Images::url($this->image)),
            'imageAlt' => $this->when($full, fn () => $this->image_alt),
            'seo' => $this->when($full, fn () => $this->seoMeta($this->title, $this->description)),
            'updatedAt' => $this->when($full, fn () => $this->updated_at?->toIso8601String()),
        ];
    }
}
