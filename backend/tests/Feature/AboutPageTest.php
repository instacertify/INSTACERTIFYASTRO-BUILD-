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
