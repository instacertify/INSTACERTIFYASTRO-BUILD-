<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePublishing;
use App\Jobs\PublishWebsite;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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
