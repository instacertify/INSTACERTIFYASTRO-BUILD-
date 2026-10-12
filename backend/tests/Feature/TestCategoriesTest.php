<?php

namespace Tests\Feature;

use App\Filament\Resources\Services\Pages\EditService;
use App\Models\Service;
use App\Models\User;
use App\Support\TestCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function testing(string $slug, ?string $group = null, array $extra = []): Service
    {
        return Service::create([
            'path' => "testing/{$slug}",
            'slug' => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'kind' => 'testing',
            'group' => $group,
            'is_published' => true,
            ...$extra,
        ]);
    }

    public function test_new_testing_services_get_a_guessed_industry_list(): void
    {
        $this->assertSame(['electrical-electronics'], $this->testing('is-16046-part-2', 'battery-testing')->test_categories);
        $this->assertSame(['metals', 'paints-coatings'], $this->testing('salt-spray-test-astm-b117', 'environmental-testing')->test_categories);
        $this->assertSame(['electrical-electronics'], $this->testing('mil-std-810h', 'environmental-testing')->test_categories);
        $this->assertSame(['it-av-equipment'], $this->testing('cispr-32-emission', 'emi-emc-testing')->test_categories);
        $this->assertSame(['it-av-equipment'], $this->testing('safety-is-13252-1-it-equipment', 'safety-testing')->test_categories);
        $this->assertSame(['electrical-electronics', 'home-kitchen'], $this->testing('is-60335', 'safety-testing')->test_categories);
        $this->assertSame(['automotive'], $this->testing('iso-16750-3-road-vehicles', 'environmental-testing')->test_categories);
        $this->assertSame(['it-av-equipment'], $this->testing('rf-conformance-5g', 'rf-conformance')->test_categories);
        $this->assertSame(['chemicals'], $this->testing('x', null, ['test_categories' => ['chemicals']])->test_categories);

        $certification = Service::create(['path' => 'certification/india/bis', 'slug' => 'bis', 'title' => 'BIS', 'kind' => 'certification']);
        $this->assertNull($certification->test_categories);
    }

    public function test_the_api_lists_industries_and_each_tests_industries(): void
    {
        $this->testing('cispr-25-automotive', 'emi-emc-testing');

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonCount(count(TestCategories::ALL), 'data.taxonomy.testCategories')
            ->assertJsonPath('data.taxonomy.testCategories.0.slug', 'electrical-electronics')
            ->assertJsonPath('data.taxonomy.testCategories.0.label', 'Electronics & Electrical')
            ->assertJsonPath('data.taxonomy.testCategories.19.label', 'General Consumer Products');

        $this->getJson('/api/v1/services?kind=testing')
            ->assertOk()
            ->assertJsonPath('data.0.testCategories', ['electrical-electronics', 'automotive']);
    }

    public function test_editors_can_move_a_test_between_industries(): void
    {
        $service = $this->testing('lux-meter-calibration', null, ['summary' => 'Calibration of lux meters.']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['test_categories' => ['furniture', 'packaging']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['furniture', 'packaging'], $service->fresh()->test_categories);
        $this->assertSame(1, Service::whereJsonContains('test_categories', 'furniture')->count());
    }

    public function test_retired_industries_point_at_a_current_one(): void
    {
        foreach (TestCategories::MOVED as $from => $to) {
            $this->assertArrayNotHasKey($from, TestCategories::ALL);
            $this->assertTrue($to === null || isset(TestCategories::ALL[$to]), "{$from} → {$to}");
        }
        $this->assertCount(20, TestCategories::ALL);
    }
}
