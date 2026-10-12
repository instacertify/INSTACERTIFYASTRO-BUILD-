<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHomePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RichSnippetsTest extends TestCase
{
    use RefreshDatabase;

    private const HOW_TO = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"HowTo","name":"Apply for BIS","step":[{"@type":"HowToStep","text":"Test the product"}]}</script>';

    private function page(): Page
    {
        return Page::create(['title' => 'BIS guide', 'path' => 'bis-guide', 'status' => 'published', 'content' => '<p>Steps.</p>']);
    }

    public function test_pages_use_automatic_markup_until_an_editor_adds_their_own(): void
    {
        $page = $this->page();
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        $this->getJson('/api/v1/pages/bis-guide')->assertJsonMissingPath('data.seo.schema')->assertJsonMissingPath('data.seo.schemaMode');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['seo.schema_mode' => StructuredData::ADD, 'seo.schema' => self::HOW_TO])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->refresh()->seo['schema'];
        $this->assertStringNotContainsString('<script', $saved, 'A pasted script tag is removed');
        $this->assertSame('HowTo', json_decode($saved, true)['@type']);

        $this->getJson('/api/v1/pages/bis-guide')
            ->assertJsonPath('data.seo.schemaMode', 'add')
            ->assertJsonPath('data.seo.schema.0.@type', 'HowTo')
            ->assertJsonPath('data.seo.schema.0.@context', 'https://schema.org')
            ->assertJsonPath('data.seo.schema.0.step.0.text', 'Test the product');
    }

    public function test_editors_can_replace_the_automatic_markup(): void
    {
        $page = $this->page();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['seo.schema_mode' => StructuredData::REPLACE, 'seo.schema' => '[{"@type":"Service","name":"BIS certification"},{"@type":"BreadcrumbList","itemListElement":[]}]'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/v1/pages/bis-guide')
            ->assertJsonPath('data.seo.schemaMode', 'replace')
            ->assertJsonCount(2, 'data.seo.schema')
            ->assertJsonPath('data.seo.schema.1.@type', 'BreadcrumbList');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['seo.schema_mode' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/v1/pages/bis-guide')->assertJsonMissingPath('data.seo.schema');
    }

    public function test_broken_or_disallowed_markup_is_refused(): void
    {
        $page = $this->page();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            '{"@type": "HowTo", "name": "missing brace"',
            '{"name": "no type"}',
            '{"@type":"Service","name":"BIS","aggregateRating":{"@type":"AggregateRating","ratingValue":"5","reviewCount":"120"}}',
            '{"@type":"Review","reviewBody":"Great"}',
            '',
        ] as $markup) {
            Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
                ->fillForm(['seo.schema_mode' => StructuredData::ADD, 'seo.schema' => $markup])
                ->call('save')
                ->assertHasFormErrors(['seo.schema']);
        }

        $this->assertArrayNotHasKey('schema', $page->refresh()->seo ?? []);
    }

    public function test_the_home_page_has_the_same_option(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        Livewire::test(ManageHomePage::class)
            ->set('data.seo.schema_mode', StructuredData::ADD)
            ->set('data.seo.schema', '{"@type":"Service","name":"Product certification in India"}')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(StructuredData::ADD, Setting::get('home')['seo']['schema_mode']);
        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.home.seo.schemaMode', 'add')
            ->assertJsonPath('data.home.seo.schema.0.name', 'Product certification in India')
            ->assertJsonMissingPath('data.home.seo.schema_mode');
    }
}
