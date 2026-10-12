<?php

namespace Tests\Feature;

use App\Jobs\PublishWebsite;
use App\Models\Author;
use App\Models\Lead;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentTransferTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'storage/framework/testing/content-transfer.jsonl';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        File::ensureDirectoryExists(dirname(base_path(self::FILE)));
    }

    protected function tearDown(): void
    {
        File::delete(base_path(self::FILE));
        parent::tearDown();
    }

    public function test_content_moves_to_another_server_without_accounts_enquiries_or_email_settings(): void
    {
        $laptop = 'http://localhost:8000/storage/';
        $author = Author::create(['name' => 'Priya Sharma', 'slug' => 'priya-sharma']);
        Post::create(['type' => 'blog', 'title' => 'BIS update', 'slug' => 'bis-update', 'status' => 'published', 'author_id' => $author->id,
            'content' => '<p><img src="'.$laptop.'images/a.webp"></p>']);
        Service::create(['path' => 'services/x', 'slug' => 'x', 'title' => 'X', 'kind' => 'certification', 'summary' => 'S',
            'detail' => ['image' => $laptop.'images/b.webp']]);
        Redirect::create(['from_path' => '/old', 'to_path' => '/new', 'is_active' => false]);
        Setting::updateOrCreate(['key' => 'site'], ['value' => ['name' => 'From the laptop']]);
        Setting::updateOrCreate(['key' => 'mail'], ['value' => ['username' => 'laptop@example.com']]);

        $this->artisan('content:export', ['file' => self::FILE])->assertSuccessful();
        $this->pretendExportedFrom($laptop);
        $this->assertStringNotContainsString('laptop@example.com', File::get(base_path(self::FILE)));

        // The server: freshly seeded content, its own admin, an enquiry and its own mailbox.
        Post::query()->delete();
        Post::create(['type' => 'blog', 'title' => 'Server only', 'slug' => 'server-only', 'status' => 'draft', 'author_id' => $author->id, 'content' => '<p>x</p>']);
        Setting::updateOrCreate(['key' => 'site'], ['value' => ['name' => 'Seeded']]);
        Setting::updateOrCreate(['key' => 'mail'], ['value' => ['username' => 'server@example.com']]);
        Setting::get('site');
        $admin = User::factory()->create();
        Lead::create(['name' => 'Asha', 'email' => 'asha@example.com', 'consent' => true]);
        config(['instacertify.frontend_rebuild_hook' => 'http://builder:8787', 'instacertify.site_publish_token' => str_repeat('t', 40)]);

        $this->artisan('content:import', ['file' => self::FILE, '--force' => true])->assertSuccessful();

        $media = Storage::disk(config('instacertify.media_disk'));
        $this->assertSame(['bis-update'], Post::pluck('slug')->all());
        $this->assertStringContainsString('src="'.$media->url('images/a.webp').'"', Post::sole()->content);
        $this->assertSame($media->url('images/b.webp'), Service::firstWhere('path', 'services/x')->detail['image']);
        $this->assertFalse(Redirect::firstWhere('from_path', '/old')->is_active);
        $this->assertSame('From the laptop', Setting::get('site')['name']);
        $this->assertSame('server@example.com', Setting::get('mail')['username']);
        $this->assertTrue($admin->is(User::sole()));
        $this->assertSame(1, Lead::count());
        Queue::assertPushed(PublishWebsite::class);
    }

    public function test_only_files_written_by_the_export_are_imported(): void
    {
        File::put(base_path(self::FILE), "{\"t\":\"posts\",\"r\":{}}\n");
        Post::create(['type' => 'blog', 'title' => 'Keep me', 'slug' => 'keep-me', 'status' => 'draft', 'content' => '<p>x</p>']);

        $this->expectExceptionMessage('not a content file');
        try {
            $this->artisan('content:import', ['file' => self::FILE, '--force' => true]);
        } finally {
            $this->assertSame(1, Post::count());
        }
    }

    private function pretendExportedFrom(string $storageUrl): void
    {
        $lines = explode("\n", File::get(base_path(self::FILE)), 2);
        $meta = json_decode($lines[0], true);
        File::put(base_path(self::FILE), json_encode([...$meta, 'storageUrl' => $storageUrl])."\n".$lines[1]);
    }
}
