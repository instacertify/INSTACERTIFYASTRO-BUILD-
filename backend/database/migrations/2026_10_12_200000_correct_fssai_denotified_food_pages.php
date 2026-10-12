<?php

use App\Models\Post;
use App\Models\Service;
use App\Models\Standard;
use App\Support\Standards\LibraryImporter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Since 17 Oct 2024 the FSS Regulations no longer require the BIS mark on packaged water, infant foods and milk powders.
 * Rebuilds the generated standards pages for those products (URLs unchanged) and corrects the post and testing page that said otherwise.
 */
return new class extends Migration
{
    private const KEYS = ['11536', '1165', '1166', '12299', '13334-p1', '13334-p2', '13428', '14433', '14542', '14543', '15757', '1656', '17945', '1806', '7021'];

    private const BACKUP = 'fssai-food-backup-2026-10.json';

    private const POSTS = [
        'isi-mark-full-form-meaning-products-list' => [
            '<tr><td>Food</td><td>Milk powder and infant food (packaged drinking water and mineral water no longer need the ISI mark since October 2024)</td></tr>' => '<tr><td>Food</td><td>None at present: packaged drinking water, mineral water, milk powder and infant food stopped needing the ISI mark in October 2024 and are regulated by FSSAI</td></tr>',
        ],
    ];

    private const SERVICES = [
        'services/testing/food-beverages/baby-food-testing' => [
            'Baby Food & Infant Formula Testing – FSSAI, BIS' => 'Baby Food & Infant Formula Testing – FSSAI',
            'Infant milk foods and cereal-based complementary foods also need the BIS ISI mark, so manufacturers test both for the FSSAI licence and for BIS certification.' => 'Since October 2024 they no longer need the BIS ISI mark, so the FSSAI licence is the legal requirement; some manufacturers keep a voluntary BIS licence because tenders and large buyers ask for it.',
            'Yes. Infant milk foods, infant formula and cereal-based complementary foods are under compulsory BIS certification, in addition to the FSSAI licence. We coordinate testing so that one set of results supports both where the methods allow.' => 'No, not since 17 October 2024, when FSSAI removed the mandatory BIS mark for infant foods. The FSSAI licence is now the legal requirement. If you keep a voluntary BIS licence, we coordinate testing so that one set of results supports both where the methods allow.',
        ],
    ];

    public function up(): void
    {
        $this->backup();

        DB::transaction(function () {
            (new LibraryImporter(database_path('data/standards')))->refreshIsiPages(self::KEYS);

            foreach (self::POSTS as $slug => $map) {
                if ($post = Post::where('type', 'blog')->firstWhere('slug', $slug)) {
                    $post->content = $this->replace($post->content, $map);
                    $post->isDirty() && $post->save();
                }
            }
            foreach (self::SERVICES as $path => $map) {
                if ($service = Service::firstWhere('path', $path)) {
                    $service->seo = $this->replace($service->seo, $map);
                    $service->detail = $this->replace($service->detail, $map);
                    $service->isDirty() && $service->save();
                }
            }
        });
    }

    public function down(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::BACKUP)) {
            return;
        }

        $backup = json_decode($disk->get(self::BACKUP), true);
        foreach ($backup['standard'] ?? [] as $id => $original) {
            if ($standard = Standard::find($id)) {
                $this->restore($standard, $original);
            }
        }
        foreach ($backup['post'] ?? [] as $slug => $original) {
            if ($post = Post::where('type', 'blog')->firstWhere('slug', $slug)) {
                $this->restore($post, $original);
            }
        }
        foreach ($backup['service'] ?? [] as $path => $original) {
            if ($service = Service::firstWhere('path', $path)) {
                $this->restore($service, $original);
            }
        }

        $disk->delete(self::BACKUP);
    }

    /** @return Collection<int, Standard> */
    private function standards(): Collection
    {
        return Standard::where('source', 'library')->where('scheme', 'isi')->get()
            ->filter(fn (Standard $s) => in_array(trim((string) $s->is_keys), self::KEYS, true));
    }

    private function backup(): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists(self::BACKUP)) {
            return;
        }

        $backup = ['standard' => [], 'post' => [], 'service' => []];
        foreach ($this->standards() as $standard) {
            $backup['standard'][$standard->id] = [
                'title' => $standard->title, 'description' => $standard->description, 'products_title' => $standard->products_title,
                'products' => $standard->products, 'faqs' => $standard->faqs, 'seo' => $standard->seo, 'updated_at' => $standard->updated_at?->toDateTimeString(),
            ];
        }
        foreach (array_keys(self::POSTS) as $slug) {
            if ($post = Post::where('type', 'blog')->firstWhere('slug', $slug)) {
                $backup['post'][$slug] = ['content' => $post->content, 'updated_at' => $post->updated_at?->toDateTimeString()];
            }
        }
        foreach (array_keys(self::SERVICES) as $path) {
            if ($service = Service::firstWhere('path', $path)) {
                $backup['service'][$path] = ['seo' => $service->seo, 'detail' => $service->detail, 'updated_at' => $service->updated_at?->toDateTimeString()];
            }
        }

        // Fresh test databases have none of these pages; an empty file would block the real backup later.
        if ($backup['standard'] || $backup['post'] || $backup['service']) {
            $disk->put(self::BACKUP, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    /** @param  array<string, mixed>  $original */
    private function restore(Model $model, array $original): void
    {
        $model->forceFill($original);
        $model->timestamps = false;
        $model->save();
    }

    /** @param  array<string, string>  $map */
    private function replace(mixed $value, array $map): mixed
    {
        if (is_string($value)) {
            return str_replace(array_keys($map), array_values($map), $value);
        }
        if (is_array($value)) {
            array_walk_recursive($value, function (&$item) use ($map) {
                if (is_string($item)) {
                    $item = str_replace(array_keys($map), array_values($map), $item);
                }
            });
        }

        return $value;
    }
};
