<?php

namespace Tests\Feature;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientLogosOnPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_strip_is_off_until_ticked_and_reaches_the_website_per_page(): void
    {
        $page = Page::create(['title' => 'Trust', 'path' => 'trust', 'status' => 'published']);
        Service::create(['path' => 'bis-crs', 'slug' => 'bis-crs', 'title' => 'BIS CRS', 'kind' => 'certification', 'show_client_logos' => true]);
        Post::create(['type' => 'blog', 'title' => 'CRS update', 'slug' => 'crs-update', 'content' => '<p>x</p>', 'status' => 'published', 'show_client_logos' => true]);
        Event::create(['title' => 'Fair', 'slug' => 'fair', 'starts_at' => now()->addMonth(), 'status' => 'published']);

        $this->getJson('/api/v1/pages/trust')->assertJsonPath('data.showClientLogos', false);
        $this->getJson('/api/v1/services/bis-crs')->assertJsonPath('data.showClientLogos', true);
        $this->getJson('/api/v1/posts/blog/crs-update')->assertJsonPath('data.showClientLogos', true);
        $this->getJson('/api/v1/events/fair')->assertJsonPath('data.showClientLogos', false);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormFieldExists('show_client_logos')
            ->fillForm(['show_client_logos' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($page->fresh()->show_client_logos);
        $this->getJson('/api/v1/pages/trust')->assertJsonPath('data.showClientLogos', true);
    }
}
