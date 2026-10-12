<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSite;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Author;
use App\Models\Event;
use App\Models\Industry;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private const SEO = [
        'title' => 'Custom meta title',
        'description' => 'A custom meta description that is long enough to be used as it is by every template.',
        'keyword' => 'bis certification',
        'og_title' => 'Social headline',
        'og_description' => 'Social description',
        'canonical' => 'https://instacertify.com/other-page',
        'robots' => 'noindex,follow',
        'reviewer' => 'Priya Sharma',
        'reviewer_credentials' => 'Lead auditor',
        'reviewed_at' => '2026-10-01',
    ];

    public function test_every_content_type_exposes_its_seo_panel(): void
    {
        $this->seed(ContentSeeder::class);
        $published = ['status' => 'published', 'published_at' => now()->subDay()];

        $service = Service::published()->firstOrFail();
        $service->update(['seo' => self::SEO]);
        $industry = Industry::published()->firstOrFail();
        $industry->update(['seo' => self::SEO]);
        Page::create(['path' => 'about-us', 'title' => 'About', 'template' => 'policy', 'content' => '<p>Hi</p>', 'seo' => self::SEO, ...$published]);
        $author = Author::create(['name' => 'Pankaj Kumar', 'slug' => 'pankaj-kumar', 'seo' => self::SEO]);
        Post::create(['type' => 'blog', 'title' => 'Guide', 'slug' => 'guide', 'content' => '<p>Hi</p>', 'author_id' => $author->id, 'seo' => self::SEO, ...$published]);
        Event::create(['title' => 'Expo', 'slug' => 'expo', 'starts_at' => now()->addMonth(), 'seo' => self::SEO, ...$published]);
        $category = StandardCategory::create(['title' => 'CRS standards', 'slug' => 'crs-standards', 'seo' => self::SEO]);
        Standard::create(['standard_category_id' => $category->id, 'title' => 'IS 13252', 'slug' => 'is-13252', 'products' => [], 'seo' => self::SEO, ...$published]);

        $standards = collect($this->getJson('/api/v1/standards')->assertOk()->json('data'))->keyBy('slug');
        foreach ([
            "services/{$service->path}" => $this->getJson("/api/v1/services/{$service->path}")->json('data.seo'),
            "industries/{$industry->slug}" => $this->getJson("/api/v1/industries/{$industry->slug}")->json('data.seo'),
            'pages/about-us' => $this->getJson('/api/v1/pages/about-us')->json('data.seo'),
            'authors/pankaj-kumar' => $this->getJson('/api/v1/authors/pankaj-kumar')->json('data.seo'),
            'posts/blog/guide' => $this->getJson('/api/v1/posts/blog/guide')->json('data.seo'),
            'events/expo' => $this->getJson('/api/v1/events/expo')->json('data.seo'),
            'standards/…/is-13252' => $this->getJson('/api/v1/standards/resources/standards/crs-standards/is-13252')->json('data.seo'),
            'standard category' => $standards['crs-standards']['seo'],
        ] as $endpoint => $seo) {
            $this->assertSame([
                'title' => 'Custom meta title',
                'description' => self::SEO['description'],
                'keyword' => 'bis certification',
                'ogTitle' => 'Social headline',
                'ogDescription' => 'Social description',
                'canonical' => 'https://instacertify.com/other-page',
                'robots' => 'noindex,follow',
                'reviewer' => 'Priya Sharma',
                'reviewerCredentials' => 'Lead auditor',
                'reviewedAt' => '2026-10-01',
            ], collect($seo)->except('image')->all(), $endpoint);
        }
    }

    public function test_blank_seo_falls_back_to_the_record(): void
    {
        Post::create(['type' => 'blog', 'title' => 'BIS guide', 'slug' => 'bis-guide', 'excerpt' => '<p>How   BIS works.</p>', 'content' => '<p>Hi</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->assertSame(
            ['title' => 'BIS guide | Instacertify', 'description' => 'How BIS works.', 'robots' => 'index,follow'],
            $this->getJson('/api/v1/posts/blog/bis-guide')->assertOk()->json('data.seo'),
        );
    }

    public function test_redirects_are_normalised_and_chains_flattened(): void
    {
        Redirect::create(['from_path' => 'https://www.instacertify.com/a/', 'to_path' => 'https://instacertify.com/b/']);
        Redirect::create(['from_path' => '/b', 'to_path' => '/c?ref=1', 'status_code' => 302]);
        Redirect::create(['from_path' => '/x', 'to_path' => 'https://www.bis.gov.in/']);
        // Loops can only come from bulk imports or toggling rows back on; they must never reach the server rules.
        Redirect::create(['from_path' => '/loop-1', 'to_path' => '/loop-2']);
        Redirect::create(['from_path' => '/loop-2', 'to_path' => '/loop-1']);
        Redirect::create(['from_path' => '/off', 'to_path' => '/a', 'is_active' => false]);

        $this->assertSame('/b', Redirect::where('from_path', '/a')->value('to_path'));
        $this->assertSame([
            ['from' => '/a', 'to' => '/c?ref=1', 'status' => 301],
            ['from' => '/b', 'to' => '/c?ref=1', 'status' => 302],
            ['from' => '/x', 'to' => 'https://www.bis.gov.in/', 'status' => 301],
        ], collect($this->getJson('/api/v1/redirects')->assertOk()->json('data'))->whereIn('from', ['/a', '/b', '/x', '/loop-1', '/loop-2', '/off'])->values()->all());
    }

    public function test_admin_rejects_redirects_that_would_break(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Redirect::create(['from_path' => '/old-page', 'to_path' => '/new-page']);
        Redirect::create(['from_path' => '/new-page', 'to_path' => '/newest-page']);

        $create = fn (string $from, string $to) => Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => $from, 'to_path' => $to, 'status_code' => 301, 'is_active' => true]);

        $create('/old-page/', '/elsewhere')->assertHasActionErrors(['from_path']);
        $create('/', '/elsewhere')->assertHasActionErrors(['from_path']);
        $create('/bad path', '/elsewhere')->assertHasActionErrors(['from_path']);
        $create('/same', 'https://instacertify.com/same/')->assertHasActionErrors(['to_path']);
        $create('/newest-page', '/old-page')->assertHasActionErrors(['to_path']);
        $create('/no-slash', 'new-page')->assertHasActionErrors(['to_path']);
        $create('/no-slash', '//evil.example')->assertHasActionErrors(['to_path']);

        $create('instacertify.com/retired/', 'https://instacertify.com/old-page/')->assertHasNoActionErrors();
        $this->assertSame('/old-page', Redirect::where('from_path', '/retired')->value('to_path'));
        $this->assertSame('/newest-page', collect($this->getJson('/api/v1/redirects')->json('data'))->firstWhere('from', '/retired')['to']);
    }

    public function test_internal_link_keywords_only_accept_pages_on_this_site(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageSite::class)
            ->set('data.autolinks', [['keywords' => 'BIS', 'url' => 'https://www.bis.gov.in/']])
            ->call('save')
            ->assertHasErrors();

        Livewire::test(ManageSite::class)
            ->set('data.autolinks', [['keywords' => 'BIS', 'url' => '/bis-certification'], ['keywords' => 'CRS', 'url' => 'https://instacertify.com/crs']])
            ->call('save')
            ->assertHasNoErrors();
    }
}
