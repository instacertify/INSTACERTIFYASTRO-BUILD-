<?php

namespace Tests\Feature;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_sections_are_edited_in_the_admin_and_served_by_the_api(): void
    {
        $page = Page::create(['title' => 'About Instacertify', 'path' => 'about', 'template' => 'about', 'status' => 'published', 'content' => '<p>Founded in 2015.</p>']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'about' => [
                    'stats_heading' => 'Our impact in numbers',
                    'stats' => [['value' => '3000+', 'label' => 'Customers']],
                    'milestones' => [['year' => '2015', 'text' => 'Founded']],
                    'values' => [['title' => 'Quality first', 'icon' => 'verified', 'text' => 'Accurate, regulation-ready outcomes.']],
                    'reasons' => [],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $data = $page->refresh()->data;
        $this->assertSame([['value' => '3000+', 'label' => 'Customers']], $data['stats']);
        $this->assertSame('2015', $data['milestones'][0]['year']);
        $this->assertSame('verified', $data['values'][0]['icon']);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormSet(['about.stats_heading' => 'Our impact in numbers'])
            ->fillForm(['title' => 'About us'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($data, $page->refresh()->data);

        $this->getJson('/api/v1/pages/about')
            ->assertOk()
            ->assertJsonPath('data.template', 'about')
            ->assertJsonPath('data.blocks.stats.0.value', '3000+')
            ->assertJsonPath('data.content', '<p>Founded in 2015.</p>');
    }

    public function test_uploaded_tile_icons_are_served_as_urls(): void
    {
        Page::create(['title' => 'About', 'path' => 'about', 'template' => 'about', 'status' => 'published', 'data' => [
            'values' => [['title' => 'Quality first', 'icon' => 'verified', 'iconImage' => 'icons/pages/2026/10/quality.webp', 'text' => 'Accurate.']],
            'reasons' => [['title' => 'Fast', 'icon' => 'bolt', 'iconImage' => null, 'text' => 'Quick.']],
        ]]);

        $blocks = $this->getJson('/api/v1/pages/about')->assertOk()->json('data.blocks');

        $this->assertStringStartsWith('http', $blocks['values'][0]['iconImage']);
        $this->assertStringEndsWith('/icons/pages/2026/10/quality.webp', $blocks['values'][0]['iconImage']);
        $this->assertSame('verified', $blocks['values'][0]['icon']);
        $this->assertNull($blocks['reasons'][0]['iconImage']);
    }

    public function test_the_quality_certification_section_is_edited_in_the_admin_and_served_by_the_api(): void
    {
        $page = Page::create(['title' => 'About', 'path' => 'about', 'template' => 'about', 'status' => 'published', 'data' => [
            'quality_points' => ['Documented processes', 'Internal audits'],
        ]]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'about.quality_standard' => 'ISO 9001:2015',
                'about.quality_heading' => 'An ISO certified company',
                'about.quality_certificate_no' => 'QMS-123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Documented processes', 'Internal audits'], array_values($page->refresh()->data['quality_points']));

        $page->update(['data' => [...$page->data, 'quality_image' => 'pages/quality/certificate.webp']]);
        $blocks = $this->getJson('/api/v1/pages/about')->assertOk()->json('data.blocks');

        $this->assertSame('ISO 9001:2015', $blocks['quality_standard']);
        $this->assertSame('QMS-123', $blocks['quality_certificate_no']);
        $this->assertStringStartsWith('http', $blocks['quality_image']);
        $this->assertStringEndsWith('/pages/quality/certificate.webp', $blocks['quality_image']);
    }

    public function test_the_iso_migration_fills_the_quality_section_once_and_rolls_back(): void
    {
        $page = Page::create(['title' => 'About', 'path' => 'about', 'template' => 'about', 'status' => 'published', 'data' => ['stats' => [['value' => '3000+', 'label' => 'Customers']]]]);
        $migration = require database_path('migrations/2026_10_13_170000_add_iso_9001_quality_section_to_about_page.php');

        $migration->up();
        $data = $page->refresh()->data;
        $this->assertSame('ISO 9001:2015', $data['quality_standard']);
        $this->assertSame('3000+', $data['stats'][0]['value']);

        $page->update(['data' => [...$data, 'quality_heading' => 'Edited in the admin']]);
        $migration->up();
        $this->assertSame('Edited in the admin', $page->refresh()->data['quality_heading']);

        $migration->down();
        $this->assertSame(['stats' => [['value' => '3000+', 'label' => 'Customers']]], $page->refresh()->data);
    }

    public function test_saving_a_hub_page_keeps_its_structured_blocks(): void
    {
        $sections = ['sections' => [['title' => 'Chemical testing', 'items' => [['title' => 'REACH', 'link_custom' => '/reach-testing']]]]];
        $page = Page::create(['title' => 'Cosmetics testing', 'path' => 'services/testing/cosmetics-testing', 'template' => 'hub', 'status' => 'published', 'data' => $sections]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Cosmetics testing in India'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('Cosmetics testing in India', $page->title);
        $this->assertSame($sections, $page->data);
    }
}
