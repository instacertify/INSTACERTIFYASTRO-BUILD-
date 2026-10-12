<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSite;
use App\Models\Author;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_guests_find_no_other_way_in(): void
    {
        foreach (['/admin/users', '/admin/leads', '/admin/job-applications/1/cv', '/admin/profile'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        foreach (['/admin/register', '/api/user', '/filament/exports/1/download', '/filament/imports/1/failed-rows/download'] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->get('/storage/legacy-urls-backup-2026-10.json')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $this->seed(ContentSeeder::class);
        $lead = Lead::create(['name' => 'Test Lead', 'email' => 'lead@example.com', 'consent' => true, 'follow_up_at' => now()->subDay()]);
        $service = Service::where('path', 'certification/india/bis-crs-mark-certification')->sole();
        $author = Author::create(['name' => 'Priya Sharma', 'slug' => 'priya-sharma']);
        $post = Post::create(['type' => 'news', 'title' => 'BIS update', 'slug' => 'bis-update', 'content' => '<p>Hello</p>', 'status' => 'published', 'author_id' => $author->id]);
        $event = Event::create(['title' => 'Electronica', 'slug' => 'electronica', 'starts_at' => now()->addMonth(), 'status' => 'published']);
        $category = StandardCategory::create(['title' => 'Mandatory CRS Standards', 'slug' => 'mandatory-crs-standards']);
        $standard = Standard::create(['standard_category_id' => $category->id, 'title' => 'CRS for monitors', 'slug' => 'crs-monitors', 'path' => 'x', 'products' => [['is_number' => '13252', 'name' => 'Monitors']]]);
        $page = Page::create(['path' => 'privacy-policy', 'title' => 'Privacy policy', 'template' => 'policy', 'content' => '<p>Policy</p>']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            '/admin',
            '/admin/leads',
            "/admin/leads/{$lead->id}/edit",
            '/admin/subscribers',
            '/admin/services',
            "/admin/services/{$service->id}/edit",
            '/admin/services/create',
            '/admin/industries',
            '/admin/testimonials',
            '/admin/posts?tab=news',
            "/admin/posts/{$post->id}/edit",
            '/admin/posts/create?tab=blog',
            '/admin/events',
            "/admin/events/{$event->id}/edit",
            '/admin/authors',
            '/admin/categories',
            '/admin/standards',
            "/admin/standards/{$standard->id}/edit",
            '/admin/standard-categories',
            '/admin/pages',
            "/admin/pages/{$page->id}/edit",
            '/admin/faqs',
            '/admin/redirects',
            '/admin/users',
            '/admin/activities',
            '/admin/manage-site',
            '/admin/vacancies',
            '/admin/vacancies/create',
            '/admin/job-applications',
            '/admin/offices',
            '/admin/offices/create',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertSame('resources/standards/mandatory-crs-standards/crs-monitors', $standard->fresh()->path);
        $this->assertSame('/news-item/bis-update', $post->url());
    }

    public function test_social_links_are_saved_and_checked_against_their_network(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageSite::class)
            ->set('data.social.youtube', 'https://www.instagram.com/instacertify/')
            ->call('save')
            ->assertHasErrors(['data.social.youtube']);

        Livewire::test(ManageSite::class)
            ->set('data.social.youtube', 'https://www.youtube.com/@instacertify')
            ->set('data.social.pinterest', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://www.youtube.com/@instacertify', Setting::get('site')['social']['youtube']);
        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.social.youtube', 'https://www.youtube.com/@instacertify')
            ->assertJsonPath('data.social.pinterest', null);
    }

    public function test_roles_limit_what_each_team_member_can_open(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']));
        $this->get('/admin/leads')->assertOk();
        $this->get('/admin/posts')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/manage-site')->assertForbidden();

        foreach (['sales', 'hr', 'retired'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/landing-pages')->assertForbidden();
            $this->get('/admin/landing-pages/create')->assertForbidden();
            $this->get('/admin/schemes')->assertForbidden();
            $this->get('/admin/industry-categories')->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get('/admin/landing-pages')->assertOk();
        $this->get('/admin/schemes')->assertOk();
        $this->get('/admin/industry-categories')->assertOk();
        $this->get('/admin/posts')->assertOk();
        $this->get('/admin/services')->assertOk();
        $this->get('/admin/leads')->assertForbidden();
        $this->get('/admin/activities')->assertForbidden();
    }

    public function test_changes_are_recorded_in_the_activity_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $service = Service::create(['path' => 'certification/india/test', 'slug' => 'test', 'title' => 'Test', 'kind' => 'certification', 'summary' => 'x']);
        $service->update(['title' => 'Renamed']);

        $this->assertDatabaseHas('activity_log', ['subject_id' => $service->id, 'event' => 'updated', 'causer_id' => $admin->id]);
    }
}
