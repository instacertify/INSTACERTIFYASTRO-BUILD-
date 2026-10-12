<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use App\Support\TestCategories;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'legacy_id', 'path', 'slug', 'title', 'kind', 'region', 'group', 'test_categories', 'summary', 'icon', 'image',
    'detail', 'seo', 'is_featured', 'is_published', 'sort',
])]
class Service extends Model
{
    use Audited, HasSeo;

    public const KINDS = [
        'certification' => 'Certification',
        'testing' => 'Product testing',
        'registration' => 'Registration & licence',
        'quality' => 'Quality assurance',
    ];

    public const REGIONS = [
        'india' => 'India',
        'emea' => 'Europe & Middle East',
        'asia' => 'Asia',
        'americas' => 'Americas',
        'global' => 'Global',
    ];

    public const GROUPS = [
        'environment-compliance' => 'EPR & environment',
        'import-export' => 'Import & export',
        'business-licenses' => 'Business licences',
        'man-power' => 'Manpower & labour',
        'factories-and-building' => 'Factories & building',
        'others' => 'Other solutions',
        'battery-testing' => 'Battery testing',
        'emi-emc-testing' => 'EMI/EMC testing',
        'environmental-testing' => 'Environmental testing',
        'safety-testing' => 'Safety testing',
        'rf-conformance' => 'RF conformance',
        'signaling-conformance' => 'Signalling conformance',
        'power-consumption-testing' => 'Power consumption testing',
        'ip-testing' => 'IP (ingress) testing',
        'food-products' => 'Food products',
        'food-contaminants' => 'Contaminants & residues',
        'food-nutrition' => 'Nutrition, labelling & shelf life',
        'water-testing' => 'Water & wastewater',
        'air-noise' => 'Air & noise monitoring',
        'soil-waste' => 'Soil, waste & sustainability',
        'fuels-lubricants' => 'Fuels & lubricants',
        'chemical-analysis' => 'Chemical & material analysis',
        'agri-inputs' => 'Fertilisers & animal feed',
        'cosmetic-testing' => 'Cosmetic & personal care tests',
        'health-products' => 'Healthcare & hygiene products',
        'packaging-materials' => 'Packaging & plastics',
        'mechanical-testing' => 'Mechanical testing',
        'food-hygiene' => 'Hygiene & plant monitoring',
        'textile-testing' => 'Textile & leather tests',
        'minerals' => 'Minerals & ores',
        'building-materials' => 'Building materials',
        'electrical-products' => 'Electrical products & solar',
        'pharma-testing' => 'Pharmaceutical testing',
        'safety-toxicology' => 'Toxicology & safety',
        'medical-devices' => 'Medical devices',
        'rubber-products' => 'Tyres & rubber',
        'home-products' => 'Kitchenware & furniture',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'test_categories' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // New testing services (including imports) start with a guessed industry list.
        static::creating(function (Service $service) {
            if ($service->kind === 'testing' && empty($service->test_categories)) {
                $service->test_categories = TestCategories::guess($service);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return '/'.$this->path;
    }
}
