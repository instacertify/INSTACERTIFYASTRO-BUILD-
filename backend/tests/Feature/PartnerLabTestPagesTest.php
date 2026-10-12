<?php

namespace Tests\Feature;

use App\Filament\Resources\Services\Pages\EditService;
use App\Models\Service;
use App\Models\User;
use App\Support\TestCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerLabTestPagesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_12_120000_add_partner_lab_test_pages.php';

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        return array_merge(...array_map(fn (string $file) => require $file, File::glob(database_path('data/testing-services/*.php'))));
    }

    private function migrate(): void
    {
        (require database_path(self::MIGRATION))->up();
    }

    public function test_every_page_is_complete_unique_and_never_names_another_lab(): void
    {
        $pages = $this->pages();
        $paths = array_map(fn (array $p) => "services/testing/{$p['category']}/{$p['slug']}", $pages);

        $this->assertGreaterThanOrEqual(120, count($pages));
        $this->assertSame($paths, array_unique($paths));
        $this->assertSame(count($pages), count(array_unique(array_column($pages, 'title'))));
        $this->assertSame(count($pages), count(array_unique(array_column($pages, 'seo_title'))));
        $this->assertSame(count($pages), count(array_unique(array_column($pages, 'seo_description'))));
        $this->assertDoesNotMatchRegularExpression('/fare\s*-?\s*lab|qualitek|itc\s*lab|swastik|equinox/i', json_encode($pages));

        foreach ($pages as $p) {
            $name = $p['slug'];
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $name);
            foreach ([$p['category'], ...($p['categories'] ?? [])] as $category) {
                $this->assertArrayHasKey($category, TestCategories::ALL, $name);
            }
            $this->assertArrayHasKey($p['group'], Service::GROUPS, $name);
            $this->assertContains($p['domain'], ['food', 'environment', 'chemical', 'cosmetic', 'health', 'material', 'electrical', 'device', 'workplace'], $name);
            $this->assertLessThanOrEqual(60, mb_strlen($p['seo_title']), $name);
            $this->assertGreaterThanOrEqual(110, mb_strlen($p['seo_description']), $name);
            $this->assertLessThanOrEqual(160, mb_strlen($p['seo_description']), $name);
            $this->assertNotEmpty($p['faqs'], $name);
            foreach ($p['parameters'] as $row) {
                $this->assertCount(2, $row, $name);
            }
            foreach ($p['related'] as $related) {
                if (substr_count($related, '/') === 3 && str_starts_with($related, 'services/testing/')) {
                    $this->assertContains($related, $paths, "{$name} links to {$related}");
                }
            }
            foreach ($p['linked_from'] ?? [] as $source) {
                $this->assertContains($source, $paths, "{$name} is linked from {$source}");
            }
            preg_match_all('#href="/(services/testing/[a-z0-9-]+/[a-z0-9-]+)"#', $p['body'], $links);
            foreach ($links[1] as $link) {
                $this->assertContains($link, $paths, "{$name} body links to {$link}");
            }
        }
    }

    public function test_earlier_pages_link_to_the_new_ones_once_and_rollback_removes_them(): void
    {
        $migration = require database_path('migrations/2026_10_12_150000_add_more_partner_lab_test_pages.php');
        $mechanical = 'services/testing/metals/mechanical-testing';
        $metallography = 'services/testing/metals/metallography-testing';
        Service::create(['path' => 'bis-certification', 'slug' => 'bis-certification', 'title' => 'BIS', 'kind' => 'certification']);

        $migration->up();
        $migration->up();

        $related = Service::where('path', $mechanical)->firstOrFail()->detail['related'];
        $this->assertContains($metallography, $related);
        $this->assertContains('services/testing/construction/concrete-testing', $related);
        $this->assertSame($related, array_unique($related));

        $ev = Service::where('path', 'services/testing/automotive/ev-battery-ais-156-testing')->firstOrFail();
        $this->assertSame('CMVR testing agency', $ev->detail['keyFacts'][0]['value']);
        $this->assertStringContainsString('CMVR', collect($ev->detail['faqs'])->last()['a']);

        $migration->down();

        $this->assertFalse(Service::where('path', $metallography)->exists());
        $this->assertNotContains($metallography, Service::where('path', $mechanical)->firstOrFail()->detail['related']);
    }

    public function test_pages_are_created_once_under_their_category_without_touching_existing_ones(): void
    {
        Service::create(['path' => 'services/testing/food-beverages/honey-testing', 'slug' => 'honey-testing', 'title' => 'Honey (admin)', 'kind' => 'testing']);

        $this->migrate();
        $this->migrate();

        $this->assertSame(count($this->pages()), Service::where('path', 'like', 'services/testing/%/%')->count());
        $this->assertSame('Honey (admin)', Service::where('path', 'services/testing/food-beverages/honey-testing')->value('title'));

        $ghee = Service::where('path', 'services/testing/food-beverages/ghee-testing')->firstOrFail();
        $this->assertSame('testing', $ghee->kind);
        $this->assertSame(['food-beverages'], $ghee->test_categories);
        $this->assertSame('Parameters', $ghee->detail['headings']['products']);
        $this->assertNotEmpty($ghee->seo['title']);
        $this->assertStringContainsString('NABL', json_encode($ghee->detail['faqs']));

        $this->getJson('/api/v1/services/services/testing/environment-water/stack-emission-monitoring')
            ->assertOk()
            ->assertJsonPath('data.detail.keyFacts.3.label', 'Sampling')
            ->assertJsonPath('data.detail.documents.0.title', 'For the site visit');
    }

    public function test_a_test_page_saves_unchanged_in_the_editor(): void
    {
        Service::create(['path' => 'bis-certification', 'slug' => 'bis-certification', 'title' => 'BIS', 'kind' => 'certification']);
        $this->migrate();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $page = Service::where('path', 'services/testing/food-beverages/pesticide-residue-testing')->firstOrFail();
        Livewire::test(EditService::class, ['record' => $page->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh();
        $this->assertSame($page->detail['table']['rows'], $saved->detail['table']['rows']);
        $this->assertSame('Parameters', $saved->detail['headings']['products']);
        $this->assertSame($page->detail['faqs'], $saved->detail['faqs']);
        $this->assertSame($page->seo['title'], $saved->seo['title']);
        $this->assertSame(['food-beverages', 'agriculture-fertilizers'], $saved->test_categories);
        $this->assertNotContains('services/indian-registration-compliances/business-licenses/fssai-central-license', $saved->detail['related']);
    }

    public function test_existing_product_tables_survive_an_editor_save(): void
    {
        $rows = [['IS 9873 (Part 1)', 'Mechanical and physical'], ['IS 15644', 'Electric toys']];
        $toys = Service::create([
            'path' => 'services/testing/toys', 'slug' => 'toys', 'title' => 'Toys', 'kind' => 'testing', 'summary' => 'Toy safety testing.',
            'detail' => ['table' => ['title' => 'Standards', 'columns' => ['Standard', 'Covers'], 'rows' => $rows]],
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditService::class, ['record' => $toys->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($rows, $toys->fresh()->detail['table']['rows']);
    }
}
