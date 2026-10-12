<?php

namespace Tests\Feature;

use App\Models\Industry;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_site_settings_are_exposed(): void
    {
        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'contact@instacertify.com')
            ->assertJsonPath('data.taxonomy.regions.india', 'India');
    }

    public function test_services_can_be_filtered(): void
    {
        $response = $this->getJson('/api/v1/services?kind=certification&region=india')->assertOk();

        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $service) {
            $this->assertSame('certification', $service['kind']);
            $this->assertSame('india', $service['region']);
            $this->assertArrayNotHasKey('detail', $service);
        }
    }

    public function test_service_detail_is_resolved_by_legacy_path(): void
    {
        $this->getJson('/api/v1/services/certification/india/bis-crs-mark-certification')
            ->assertOk()
            ->assertJsonPath('data.title', 'BIS CRS Registration')
            ->assertJsonPath('data.detail.keyFacts.0.value', '4–8 weeks')
            ->assertJsonStructure(['data' => ['seo' => ['title', 'description']]]);
    }

    public function test_unknown_service_returns_404(): void
    {
        $this->getJson('/api/v1/services/certification/nowhere')->assertNotFound();
    }

    public function test_industry_splits_linked_services_into_certification_and_testing(): void
    {
        $this->getJson('/api/v1/industries/electronic-industry')
            ->assertOk()
            ->assertJsonPath('data.certification.services.0.path', 'certification/india/bis-crs-mark-certification')
            ->assertJsonMissingPath('data.certification.services.5')
            ->assertJsonPath('data.testing.services.0.path', 'services/testing/electronics-electricals-testing');
    }

    public function test_industry_without_picked_tests_falls_back_to_its_testing_industries(): void
    {
        $industry = Industry::where('slug', 'software-industry')->firstOrFail();
        $industry->update(['testing_paths' => [], 'test_categories' => ['toys-childcare']]);

        $this->getJson('/api/v1/industries/software-industry')
            ->assertJsonPath('data.testing.services.0.path', 'services/testing/toys')
            ->assertJsonPath('data.testing.categories.0', ['slug' => 'toys-childcare', 'label' => 'Toys & Childcare Products', 'url' => '/services/testing/toys-childcare', 'count' => 1]);
    }
}
