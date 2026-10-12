<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHomePage;
use App\Filament\Pages\ManageSite;
use App\Models\LandingPage;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\TestCategories;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IconAlignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_each_home_section_starts_at_its_current_layout_and_saves_the_chosen_position(): void
    {
        Livewire::test(ManageHomePage::class)
            ->assertSet('data.whatWeDo.iconAlign', 'center')
            ->assertSet('data.process.iconAlign', 'center')
            ->assertSet('data.whyUs.iconAlign', 'left')
            ->set('data.whyUs.iconAlign', 'right')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('right', Setting::get('home')['whyUs']['iconAlign']);
        $this->getJson('/api/v1/site')->assertJsonPath('data.home.whyUs.iconAlign', 'right');
    }

    public function test_an_unknown_position_is_rejected(): void
    {
        Livewire::test(ManageHomePage::class)
            ->set('data.whyUs.iconAlign', 'diagonal')
            ->call('save')
            ->assertHasErrors(['data.whyUs.iconAlign']);
    }

    public function test_landing_page_positions_and_uploaded_step_and_number_icons_reach_the_website(): void
    {
        LandingPage::create([
            'title' => 'Icons', 'path' => 'lp/icons', 'status' => 'published',
            'content' => [
                'stats_icon_align' => 'center',
                'stats' => [['value' => '9', 'label' => 'Offices', 'iconImage' => 'icons/landing/2026/10/pin.png']],
                'steps' => ['icon_align' => 'right', 'items' => [['title' => 'Apply', 'icon' => 'edit_document', 'iconImage' => 'icons/landing/2026/10/form.svg']]],
            ],
        ]);

        $content = $this->getJson('/api/v1/landing-pages/lp/icons')->assertOk()->json('data.content');

        $this->assertSame('center', $content['stats_icon_align']);
        $this->assertSame('right', $content['steps']['icon_align']);
        $this->assertStringEndsWith('/icons/landing/2026/10/pin.png', $content['stats'][0]['iconImage']);
        $this->assertStringEndsWith('/icons/landing/2026/10/form.svg', $content['steps']['items'][0]['iconImage']);
    }

    public function test_service_steps_take_an_uploaded_icon(): void
    {
        $service = Service::where('path', 'certification/india/bis-crs-mark-certification')->sole();
        $service->update(['detail' => [...($service->detail ?? []), 'steps' => [['title' => 'Test', 'icon' => 'science', 'iconImage' => 'icons/services/2026/10/lab.webp']]]]);

        $this->getJson('/api/v1/services/certification/india/bis-crs-mark-certification')
            ->assertJsonPath('data.detail.steps.0.icon', 'science')
            ->assertJsonPath('data.detail.steps.0.iconImage', fn (string $url) => str_starts_with($url, 'http') && str_ends_with($url, '/icons/services/2026/10/lab.webp'));
    }

    public function test_testing_industry_tiles_take_a_position_from_site_settings(): void
    {
        Livewire::test(ManageSite::class)
            ->assertSet('data.testCategories.iconAlign', 'left')
            ->set('data.testCategories.iconAlign', 'center')
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.taxonomy.testCategoryIconAlign', 'center')
            ->assertJsonCount(count(TestCategories::ALL), 'data.taxonomy.testCategories');
    }
}
