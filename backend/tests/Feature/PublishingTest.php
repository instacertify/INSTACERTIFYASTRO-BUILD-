<?php

namespace Tests\Feature;

use App\Console\Commands\PublishDueContent;
use App\Filament\Pages\ManagePublishing;
use App\Jobs\PublishWebsite;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Post;
use App\Models\Scheme;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vacancy;
use Filament\Actions\Testing\TestAction;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a-test-token-that-is-long-enough-123456';

    private function configure(): void
    {
        config(['instacertify.frontend_rebuild_hook' => 'http://builder:8787', 'instacertify.site_publish_token' => self::TOKEN]);
    }

    public function test_saved_content_is_published_shortly_after(): void
    {
        $this->configure();
        Queue::fake();

        Page::create(['title' => 'About', 'path' => 'about', 'status' => 'published']);
        Queue::assertPushed(PublishWebsite::class, fn (PublishWebsite $job) => $job->delay !== null);

        Queue::fake();
        Setting::updateOrCreate(['key' => 'mail'], ['value' => ['enabled' => false]]);
        Lead::create(['name' => 'Asha', 'email' => 'asha@example.com', 'consent' => true]);
        Queue::assertNotPushed(PublishWebsite::class);

        Setting::updateOrCreate(['key' => 'site'], ['value' => ['name' => 'Instacertify']]);
        Queue::assertPushed(PublishWebsite::class);
    }

    public function test_saving_without_a_change_leaves_the_website_alone(): void
    {
        $this->configure();
        Queue::fake();
        $page = Page::create(['title' => 'About', 'path' => 'about', 'status' => 'published']);
        $site = Setting::create(['key' => 'site', 'value' => ['name' => 'Instacertify']]);
        Queue::fake();

        $page->save();
        $this->travel(1)->minute();
        $page->touch();
        $site->update(['value' => ['name' => 'Instacertify']]);
        Queue::assertNotPushed(PublishWebsite::class);

        $page->update(['title' => 'About us']);
        Queue::assertPushedTimes(PublishWebsite::class, 1);
        // One publish waits at a time; let this one start so the next change queues another.
        (new UniqueLock(app(Repository::class)))->release(new PublishWebsite);
        $site->update(['value' => ['name' => 'Instacertify India']]);
        Queue::assertPushedTimes(PublishWebsite::class, 2);
    }

    public function test_picking_schemes_alone_publishes(): void
    {
        $this->configure();
        Queue::fake();
        $scheme = Scheme::create(['name' => 'Test scheme']);
        $post = Post::create(['type' => 'blog', 'title' => 'CRS update', 'slug' => 'crs-update', 'content' => '<p>x</p>', 'status' => 'published']);
        $post->schemes()->detach();
        Queue::fake();

        $post->schemes()->sync([$scheme->id]);
        Queue::assertPushed(PublishWebsite::class);

        Queue::fake();
        $post->schemes()->sync([]);
        Queue::assertPushed(PublishWebsite::class);
    }

    public function test_dates_publish_only_when_they_change_what_the_website_shows(): void
    {
        $this->configure();
        Queue::fake();
        $this->travelTo('2026-10-13 10:00:00');
        Event::create(['title' => 'Fair', 'slug' => 'fair', 'starts_at' => '2026-10-11 09:00', 'ends_at' => '2026-10-13 18:00', 'status' => 'published']);
        Event::create(['title' => 'Webinar', 'slug' => 'webinar', 'starts_at' => '2026-10-20 09:00', 'status' => 'published']);
        Post::create(['type' => 'blog', 'title' => 'Scheduled', 'slug' => 'scheduled', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => '2026-10-14 06:00']);
        Vacancy::create(['title' => 'Executive', 'slug' => 'executive', 'department' => 'Certification', 'summary' => 'x', 'description' => '<p>x</p>', 'status' => 'open', 'closes_at' => '2026-10-15']);
        Queue::fake();

        $this->artisan('site:publish-due')->expectsOutputToContain('First check')->assertSuccessful();
        $this->travelTo('2026-10-13 23:59:00');
        $this->artisan('site:publish-due')->expectsOutputToContain('Nothing on the website has changed')->assertSuccessful();
        Queue::assertNotPushed(PublishWebsite::class);

        $this->travelTo('2026-10-14 00:01:00');
        $this->artisan('site:publish-due')->expectsOutputToContain('1 events ended')->assertSuccessful();
        Queue::assertPushedTimes(PublishWebsite::class, 1);

        $this->assertSame(['1 scheduled articles went live'], PublishDueContent::due(now(), now()->setTime(7, 0)));
        $this->assertSame(['1 vacancies closed'], PublishDueContent::due(now()->setDate(2026, 10, 15), now()->setDate(2026, 10, 16)));
        $this->assertSame([], PublishDueContent::due(now()->setDate(2026, 10, 16), now()->setDate(2026, 10, 20)));
        $this->assertSame(['1 events ended'], PublishDueContent::due(now()->setDate(2026, 10, 20), now()->setDate(2026, 10, 21)));
    }

    public function test_nothing_is_queued_where_publishing_is_not_set_up(): void
    {
        Queue::fake();

        Page::create(['title' => 'About', 'path' => 'about', 'status' => 'published']);

        Queue::assertNotPushed(PublishWebsite::class);
    }

    public function test_the_job_asks_the_builder_with_the_shared_secret(): void
    {
        $this->configure();
        Http::fake(['builder:8787/publish' => Http::response(['status' => 'building'], 202)]);

        (new PublishWebsite)->handle();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://builder:8787/publish'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN));
    }

    public function test_the_job_retries_when_the_builder_is_down(): void
    {
        $this->configure();
        Http::fake(['builder:8787/*' => Http::response(['error' => 'Unauthorised'], 401)]);

        $this->expectException(RuntimeException::class);
        (new PublishWebsite)->handle();
    }

    public function test_editors_see_the_status_and_can_publish_now(): void
    {
        $this->configure();
        Http::fake([
            'builder:8787/status' => Http::response(['status' => 'idle', 'publishedAt' => '2026-10-12T10:00:00Z', 'pages' => 2460]),
            'builder:8787/publish' => Http::response(['status' => 'building'], 202),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'sales']));
        $this->get('/admin/publish-website')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get('/admin/publish-website')->assertOk()->assertSee('Up to date.')->assertSee('12 Oct 2026, 3:30 pm (2,460 pages)', false);

        Livewire::test(ManagePublishing::class)->callAction(TestAction::make('publish'))->assertNotified('Publishing started');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://builder:8787/publish');
    }

    public function test_the_status_explains_problems_in_plain_words(): void
    {
        $this->assertStringContainsString('not set up', ManagePublishing::statusLines(false, null)[0]);
        $this->assertStringContainsString('not answering', ManagePublishing::statusLines(true, null)[0]);

        $failed = ManagePublishing::statusLines(true, ['status' => 'failed', 'error' => "Build failed (exit 1):\nstack trace", 'publishedAt' => '2026-10-11T22:00:00Z']);
        $this->assertStringContainsString('still shows the previous version', $failed[0]);
        $this->assertSame('Reason: Build failed (exit 1):', $failed[2]);

        $building = ManagePublishing::statusLines(true, ['status' => 'building', 'startedAt' => '2026-10-12T10:00:00Z', 'queued' => true]);
        $this->assertStringContainsString('Newer changes will be published straight after', $building[0]);
    }
}
