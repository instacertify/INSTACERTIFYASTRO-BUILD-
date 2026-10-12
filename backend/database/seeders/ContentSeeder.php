<?php

namespace Database\Seeders;

use App\Models\Industry;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $site = $this->json('site.json');
        $existing = Setting::firstWhere('key', 'site')?->value ?? [];
        Setting::updateOrCreate(['key' => 'site'], ['value' => array_replace($site, $existing)]);

        foreach ($this->json('catalog.json') as $i => $item) {
            $detail = $this->detailFor($item['path']);
            $seo = array_filter([
                'title' => $detail['seoTitle'] ?? null,
                'description' => $detail['seoDescription'] ?? null,
            ]) ?: null;

            if ($detail) {
                if (! empty($detail['overview']) && empty($detail['body'])) {
                    $detail['body'] = collect($detail['overview'])->map(fn ($p) => '<p>'.e($p).'</p>')->implode('');
                }
                unset($detail['overview'], $detail['seoTitle'], $detail['seoDescription']);
            }

            Service::updateOrCreate(['path' => $item['path']], [
                'slug' => basename($item['path']),
                'title' => $item['title'],
                'kind' => $item['kind'],
                'region' => $item['region'] ?? null,
                'group' => $item['group'] ?? null,
                'summary' => $item['summary'],
                'is_featured' => $item['featured'] ?? false,
                'sort' => $i,
                ...($detail ? ['detail' => $detail, 'seo' => $seo] : []),
            ]);
        }

        $testing = collect($this->json('catalog.json'))->where('kind', 'testing')->pluck('path')->all();

        foreach ($this->json('industries.json') as $i => $item) {
            $paths = $item['services'] ?? [];
            Industry::updateOrCreate(['slug' => $item['slug']], [
                'name' => $item['name'],
                'icon' => $item['icon'] ?? null,
                'summary' => $item['summary'],
                'intro' => $item['intro'] ?? null,
                'highlights' => $item['highlights'] ?? [],
                'service_paths' => array_values(array_diff($paths, $testing)),
                'testing_paths' => array_values(array_intersect($paths, $testing)),
                'sort' => $i,
            ]);
        }

        foreach ($this->json('testimonials.json') as $i => $item) {
            Testimonial::updateOrCreate(['name' => $item['name']], [...$item, 'sort' => $i]);
        }
    }

    private function detailFor(string $path): ?array
    {
        $file = config('instacertify.content_path').'/services/'.basename($path).'.json';

        if (! File::exists($file)) {
            return null;
        }

        $detail = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
        unset($detail['path']);

        return $detail;
    }

    private function json(string $name): array
    {
        $file = config('instacertify.content_path').'/'.$name;

        if (! File::exists($file)) {
            throw new RuntimeException("Content file not found: {$file}");
        }

        return json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
    }
}
