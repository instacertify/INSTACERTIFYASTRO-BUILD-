<?php

namespace Tests\Feature;

use App\Models\Redirect;
use App\Models\Standard;
use App\Models\StandardCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LedLightingRevisionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_13_120000_revise_led_lighting_standards_and_wearable_glasses.php');
    }

    public function test_lighting_pages_move_to_the_2026_editions_and_the_duplicate_xr_page_redirects(): void
    {
        Storage::fake('local');
        StandardCategory::create(['title' => 'Mandatory BIS Standard', 'slug' => 'mandatory-bis-standard']);
        StandardCategory::create(['title' => 'ISO Management System', 'slug' => 'iso-management-system']);
        $category = StandardCategory::create(['title' => 'Mandatory CRS Standards', 'slug' => 'mandatory-crs-standards']);
        $page = fn (string $slug, array $extra) => Standard::create(['standard_category_id' => $category->id, 'title' => $slug, 'slug' => $slug, 'status' => 'published', 'scheme' => 'crs', ...$extra]);
        $chains = $page('crs-mark-lighting-chain-rope-lights', [
            'description' => '<p>Compliance with IS 10322 (Part 5/Sec 4) and applicable standards.</p>',
            'products' => [['is_number' => '10322 (Part 5)Section 9: 2017', 'name' => 'Lighting Chain (Rope Lights)'], ['is_number' => 'IS 10322 (Part 5/Sec 7)', 'name' => 'LED Lighting Chains (Rope Lights)']],
            'faqs' => [['q' => 'What is it?', 'a' => 'It ensures compliance with IS 10322 (Part 5/Sec 8): 2013 for safety.']],
        ]);
        $luminaires = $page('crs-mark-led-luminaries', ['products' => [['is_number' => '10322 (Part 5/Sec 1)', 'name' => 'Fixed General Purpose LED Luminaires']]]);
        $original = $page('crs-mark-extended-reality-products-arvrmr', ['source' => 'library', 'products' => [['is_number' => 'IS/IEC 62368-1:2023', 'name' => 'Extended Reality Products']]]);
        $duplicate = $page('crs-mark-extended-reality-products-ar-vr-mr', ['source' => 'library', 'products' => [['is_number' => 'IS/IEC 62368-1:2023', 'name' => 'Extended Reality Products']]]);

        $this->migration()->up();

        $chains->refresh();
        $this->assertSame([['IS 10322 (Part 5/Sec 7):2026', 'Lighting Chain (Rope Lights)', 'IS 10322 (Part 5/Sec 7):2017']], array_map(fn ($row) => [$row['is_number'], $row['name'], $row['replaces']], $chains->products));
        $this->assertSame('<p>Compliance with IS 10322 (Part 5/Sec 7):2026 and applicable standards.</p>', $chains->description);
        $this->assertSame('It ensures compliance with IS 10322 (Part 5/Sec 7):2026 for safety.', $chains->faqs[0]['a']);
        $this->assertSame('Can I still test to IS 10322 (Part 5/Sec 7):2017?', $chains->faqs[1]['q']);
        $this->assertStringContainsString('until 2 February 2027', $chains->faqs[1]['a']);
        $this->assertSame(['Fixed General Purpose LED Luminaires', 'Fancy Lights'], array_column($luminaires->fresh()->products, 'name'));
        $this->assertNull(Standard::find($duplicate->id));
        $this->assertSame('/'.$original->path, Redirect::where('from_path', '/'.$duplicate->path)->value('to_path'));

        $this->migration()->down();

        $this->assertSame('10322 (Part 5)Section 9: 2017', $chains->fresh()->products[0]['is_number']);
        $this->assertNotNull(Standard::find($duplicate->id));
        $this->assertFalse(Redirect::where('from_path', '/'.$duplicate->path)->exists());
    }
}
