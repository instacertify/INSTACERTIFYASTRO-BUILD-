<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Service;
use App\Models\Standard;
use App\Models\StandardCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IsIec62368MigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_13_100000_move_it_av_products_to_is_iec_62368.php');
    }

    public function test_it_av_pages_move_to_the_new_standard_and_can_be_restored(): void
    {
        Storage::fake('local');
        $category = StandardCategory::create(['title' => 'Mandatory CRS Standards', 'slug' => 'mandatory-crs-standards']);
        $amplifier = ['is_number' => 'IS 616', 'name' => 'Amplifiers'];
        $standard = Standard::create([
            'standard_category_id' => $category->id, 'title' => 'CRS Mark for Amplifiers', 'slug' => 'crs-mark-amplifiers', 'status' => 'published',
            'description' => '<p>Compliance with IS 616:2017 and applicable audio standards.</p>',
            'products' => [$amplifier, $amplifier, ['is_number' => '13252 (Part 1):2010', 'name' => 'Adaptors'], ['is_number' => 'IS 302 (Part 1)', 'name' => 'Adaptors']],
            'faqs' => [['q' => 'Is CRS mandatory for amplifiers?', 'a' => 'Yes, under IS 616:2017 or IS 13252:2010 rules.']],
        ]);
        $service = Service::create(['path' => 'is-iec-62368-1', 'slug' => 'is-iec-62368-1', 'title' => 'IS/IEC 62368-1', 'kind' => 'testing', 'detail' => [
            'sections' => [['html' => '<ol><li><strong>Testing Fees:</strong> Charged by the lab based on the complexity and number of models (Series guidelines apply).</li></ol>']],
            'faqs' => [],
        ]]);
        $post = Post::create(['type' => 'blog', 'title' => 'How to get BIS CRS', 'slug' => 'how-to-get-bis-crs', 'content' => '<p>Laptops need IS 13252.</p>', 'status' => 'published']);

        $this->migration()->up();

        $standard->refresh();
        $this->assertSame([
            ['IS/IEC 62368-1:2023', 'Amplifiers', 'IS 616:2017'],
            ['IS/IEC 62368-1:2023', 'Adaptors', 'IS 13252 (Part 1):2010'],
            ['IS 302 (Part 1)', 'Adaptors', null],
        ], array_map(fn ($row) => [$row['is_number'], $row['name'], $row['replaces'] ?? null], $standard->products));
        $this->assertSame('<p>Compliance with IS/IEC 62368-1:2023 and applicable audio standards.</p>', $standard->description);
        $this->assertSame('Yes, under IS/IEC 62368-1:2023 rules.', $standard->faqs[0]['a']);
        $this->assertSame('Can I still register this product under IS 616:2017?', $standard->faqs[1]['q']);
        $this->assertStringContainsString('until 1 November 2028', $standard->faqs[1]['a']);
        $this->assertStringContainsString(' 616 ', $standard->is_keys);
        $this->assertStringContainsString('Start from ₹35,000', $service->fresh()->detail['sections'][0]['html']);
        $this->assertSame('How much does IS/IEC 62368-1 testing cost?', $service->fresh()->detail['faqs'][0]['q']);
        $this->assertStringStartsWith('<p><strong>Update:</strong> IS 13252 (Part 1):2010 and IS 616:2017 have been superseded', $post->fresh()->content);

        $this->migration()->down();

        $this->assertSame('IS 616', $standard->fresh()->products[0]['is_number']);
        $this->assertCount(4, $standard->fresh()->products);
        $this->assertSame('<p>Laptops need IS 13252.</p>', $post->fresh()->content);
        $this->assertSame([], $service->fresh()->detail['faqs']);
    }
}
