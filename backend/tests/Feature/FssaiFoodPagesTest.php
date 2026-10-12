<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Service;
use App\Models\Standard;
use App\Models\StandardCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FssaiFoodPagesTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_12_200000_correct_fssai_denotified_food_pages.php');
    }

    private function libraryPage(StandardCategory $category, string $slug, string $is): Standard
    {
        return Standard::create([
            'standard_category_id' => $category->id, 'source' => 'library', 'scheme' => 'isi', 'slug' => $slug,
            'title' => "ISI Mark – {$is}", 'description' => '<p>Must carry the ISI mark.</p>', 'status' => 'published', 'published_at' => now()->subYear(),
            'products' => [['is_number' => $is, 'name' => 'Product', 'status' => 'mandatory', 'since' => '29 Mar 2001', 'mandatory_from' => '2001-03-29']],
            'faqs' => [['q' => 'Is BIS certification mandatory?', 'a' => 'Yes.']],
        ]);
    }

    public function test_food_standards_become_voluntary_at_the_same_url_and_can_be_restored(): void
    {
        Storage::fake('local');
        $mandatory = StandardCategory::create(['title' => 'Mandatory BIS Standard', 'slug' => 'mandatory-bis-standard']);
        $water = $this->libraryPage($mandatory, 'isi-mark-packaged-drinking-water-is-14543', 'IS 14543');
        $chemical = $this->libraryPage($mandatory, 'isi-mark-phosphorus-oxychloride-technical-is-11657', 'IS 11657');
        $path = $water->path;

        $this->migration()->up();

        $water->refresh();
        $this->assertSame($path, $water->path);
        $this->assertSame('voluntary', $water->regulation_status);
        $this->assertNull($water->mandatory_from);
        $this->assertSame('Food, Dairy & Beverages', $water->sector);
        $this->assertStringContainsString('is now optional', $water->description);
        $this->assertStringContainsString('Scheme of Testing', $water->description);
        $this->assertStringStartsWith('No, not any more.', $water->faqs[0]['a']);
        $this->assertArrayNotHasKey('note', $water->products[0]);
        $this->assertSame('mandatory', $chemical->fresh()->regulation_status);

        $this->migration()->down();

        $water->refresh();
        $this->assertSame('mandatory', $water->regulation_status);
        $this->assertSame('<p>Must carry the ISI mark.</p>', $water->description);
        Storage::disk('local')->assertMissing('fssai-food-backup-2026-10.json');
    }

    public function test_post_and_testing_page_are_corrected(): void
    {
        Storage::fake('local');
        $post = Post::create([
            'type' => 'blog', 'title' => 'ISI mark', 'slug' => 'isi-mark-full-form-meaning-products-list', 'status' => 'published', 'published_at' => now()->subYear(),
            'content' => '<table><tr><td>Food</td><td>Milk powder and infant food (packaged drinking water and mineral water no longer need the ISI mark since October 2024)</td></tr></table>',
        ]);
        $service = Service::create([
            'path' => 'services/testing/food-beverages/baby-food-testing', 'slug' => 'baby-food-testing', 'title' => 'Infant & Baby Food Testing', 'kind' => 'testing',
            'seo' => ['title' => 'Baby Food & Infant Formula Testing – FSSAI, BIS'],
            'detail' => ['faqs' => [['q' => 'Does infant food need BIS certification as well as FSSAI?', 'a' => 'Yes. Infant milk foods, infant formula and cereal-based complementary foods are under compulsory BIS certification, in addition to the FSSAI licence. We coordinate testing so that one set of results supports both where the methods allow.']]],
        ]);

        $this->migration()->up();

        $this->assertStringContainsString('None at present', $post->fresh()->content);
        $service->refresh();
        $this->assertSame('Baby Food & Infant Formula Testing – FSSAI', $service->seo['title']);
        $this->assertStringStartsWith('No, not since 17 October 2024', $service->detail['faqs'][0]['a']);
    }
}
