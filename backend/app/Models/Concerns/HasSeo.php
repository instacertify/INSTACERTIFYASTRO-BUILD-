<?php

namespace App\Models\Concerns;

use App\Support\Images;
use App\Support\StructuredData;

trait HasSeo
{
    public function initializeHasSeo(): void
    {
        $this->mergeCasts(['seo' => 'array']);
    }

    /**
     * Resolved meta for the frontend, falling back to the record's own title/summary.
     *
     * @return array<string, mixed>
     */
    public function seoMeta(string $title, ?string $description = null): array
    {
        $seo = $this->seo ?? [];

        return array_filter([
            'title' => $seo['title'] ?? "{$title} | Instacertify",
            'description' => $seo['description'] ?? ($description ? str($description)->stripTags()->squish()->limit(160)->toString() : null),
            'keyword' => $seo['keyword'] ?? null,
            'ogTitle' => $seo['og_title'] ?? null,
            'ogDescription' => $seo['og_description'] ?? null,
            'image' => Images::url($seo['image'] ?? null),
            'canonical' => $seo['canonical'] ?? null,
            'robots' => $seo['robots'] ?? 'index,follow',
            'reviewer' => $seo['reviewer'] ?? null,
            'reviewerCredentials' => $seo['reviewer_credentials'] ?? null,
            'reviewedAt' => $seo['reviewed_at'] ?? null,
            ...StructuredData::forApi($seo),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
