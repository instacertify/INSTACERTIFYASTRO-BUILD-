<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'icon', 'icon_image', 'image', 'summary', 'intro', 'highlights', 'service_paths', 'certification_text', 'testing_paths', 'test_categories', 'testing_text', 'seo', 'is_published', 'sort', 'show_client_logos'])]
class Industry extends Model
{
    use Audited, HasSeo;

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'service_paths' => 'array',
            'testing_paths' => 'array',
            'test_categories' => 'array',
            'is_published' => 'boolean',
            'show_client_logos' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Certifications, registrations and licences picked for this industry.
     *
     * @return Collection<int, Service>
     */
    public function certificationServices(): Collection
    {
        return $this->servicesAt($this->service_paths ?? [])->where('kind', '!=', 'testing')->values();
    }

    /**
     * Testing services picked for this industry; when none are picked, the first tests from its product-testing industries.
     *
     * @return Collection<int, Service>
     */
    public function testingServices(): Collection
    {
        $picked = $this->servicesAt([...($this->testing_paths ?? []), ...($this->service_paths ?? [])])
            ->where('kind', 'testing')->values();

        if ($picked->isNotEmpty() || empty($this->test_categories)) {
            return $picked;
        }

        return Service::published()->where('kind', 'testing')
            ->where(fn (Builder $q) => collect($this->test_categories)->each(fn (string $c) => $q->orWhereJsonContains('test_categories', $c)))
            ->orderByDesc('is_featured')->orderBy('sort')->limit(4)->get();
    }

    /**
     * @param  list<string>  $paths
     * @return Collection<int, Service>
     */
    private function servicesAt(array $paths): Collection
    {
        return Service::published()
            ->whereIn('path', $paths)
            ->get()
            ->sortBy(fn (Service $s) => array_search($s->path, $paths))
            ->values();
    }
}
