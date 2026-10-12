<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const FILES = [
        'food-quality-hygiene',
        'textiles-agri-minerals',
        'construction',
        'electrical-automotive',
        'pharma-medical',
        'environment-workplace',
        'packaging-home-metals',
    ];

    private const MAX_RELATED = 8;

    public function up(): void
    {
        (require database_path('migrations/2026_10_12_120000_add_partner_lab_test_pages.php'))->up();

        foreach ($this->pages() as $page) {
            $path = $this->path($page);
            if (! Service::where('path', $path)->exists()) {
                continue;
            }

            foreach ($page['linked_from'] ?? [] as $source) {
                $service = Service::firstWhere('path', $source);
                $detail = $service?->detail ?? [];
                $related = $detail['related'] ?? [];
                if (! $service || in_array($path, $related, true) || count($related) >= self::MAX_RELATED) {
                    continue;
                }

                $detail['related'] = [...$related, $path];
                $service->update(['detail' => $detail]);
            }
        }
    }

    public function down(): void
    {
        $paths = array_map($this->path(...), $this->pages());

        Service::whereIn('path', array_unique(array_merge(...array_map(fn (array $page) => $page['linked_from'] ?? [], $this->pages()))))
            ->get()
            ->each(function (Service $service) use ($paths) {
                $detail = $service->detail;
                $detail['related'] = array_values(array_diff($detail['related'] ?? [], $paths));
                $service->update(['detail' => $detail]);
            });

        Service::whereIn('path', $paths)->delete();
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        return array_merge(...array_map(fn (string $file) => require database_path("data/testing-services/{$file}.php"), self::FILES));
    }

    /** @param  array<string, mixed>  $page */
    private function path(array $page): string
    {
        return "services/testing/{$page['category']}/{$page['slug']}";
    }
};
