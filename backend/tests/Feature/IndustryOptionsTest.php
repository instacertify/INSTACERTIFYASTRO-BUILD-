<?php

namespace Tests\Feature;

use App\Filament\Resources\Industries\Pages\CreateIndustry;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndustryOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_an_industry_needs_both_a_certification_and_a_testing_option(): void
    {
        Livewire::test(CreateIndustry::class)
            ->fillForm(['name' => 'Furniture', 'slug' => 'furniture-industry', 'summary' => 'Office and home furniture.'])
            ->call('create')
            ->assertHasFormErrors(['service_paths' => 'required', 'test_categories' => 'required']);

        Livewire::test(CreateIndustry::class)
            ->fillForm([
                'name' => 'Furniture',
                'slug' => 'furniture-industry',
                'summary' => 'Office and home furniture.',
                'certification_text' => 'BIS ISI marks for office chairs.',
                'service_paths' => ['certification/india/bis-isi-mark-domestic-manufacturers'],
                'test_categories' => ['furniture'],
                'testing_text' => 'Strength and stability tests.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $industry = Industry::where('slug', 'furniture-industry')->firstOrFail();
        $this->assertSame(['furniture'], $industry->test_categories);

        $this->getJson('/api/v1/industries/furniture-industry')
            ->assertJsonPath('data.certification.text', 'BIS ISI marks for office chairs.')
            ->assertJsonPath('data.certification.services.0.path', 'certification/india/bis-isi-mark-domestic-manufacturers')
            ->assertJsonPath('data.testing.services', [])
            ->assertJsonPath('data.testing.categories.0.url', '/services/testing/furniture')
            ->assertJsonPath('data.testing.categories.0.count', 0);
    }
}
