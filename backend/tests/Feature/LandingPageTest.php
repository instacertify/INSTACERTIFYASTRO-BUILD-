<?php

namespace Tests\Feature;

use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bis_ad_page_is_seeded_without_fmcs(): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->firstOrFail();

        $this->assertSame('published', $page->status);
        $this->assertFalse($page->indexable);
        $this->assertStringNotContainsStringIgnoringCase('fmcs', json_encode($page->content));
    }

    public function test_the_bis_ad_page_has_the_fee_finder_and_no_consulting_prices(): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->firstOrFail();

        $this->assertTrue($page->content['finder']['enabled']);
        $this->assertSame([7000, 14500, 27000, 27000], [
            $page->content['finder']['crs_fee_micro'], $page->content['finder']['crs_fee_small'],
            $page->content['finder']['crs_fee_medium'], $page->content['finder']['crs_fee_large'],
        ]);
        $text = json_encode([$page->content, $page->seo], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('9,999', $text);
        $this->assertStringNotContainsString('20,999', $text);
    }

    public function test_api_returns_published_pages_and_hides_ad_pages_from_search(): void
    {
        LandingPage::create(['title' => 'Draft', 'path' => 'lp/draft', 'status' => 'draft', 'content' => ['hero' => ['headline' => 'Draft']]]);

        $this->getJson('/api/v1/landing-pages')
            ->assertOk()
            ->assertJsonPath('data.0.url', '/lp/bis-certification')
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/landing-pages/lp/bis-certification')
            ->assertOk()
            ->assertJsonPath('data.content.hero.headline', 'BIS Certification for Your Product – ISI Mark & CRS')
            ->assertJsonPath('data.seo.robots', 'noindex,follow')
            ->assertJsonPath('data.content.related.keywords.0', 'LED lights & drivers');

        $this->getJson('/api/v1/landing-pages/lp/draft')->assertNotFound();
    }

    public function test_ad_pages_are_published_noindex_once_the_service_catalogue_exists(): void
    {
        Service::create(['path' => 'certification/india/imei-icdr-registration', 'slug' => 'imei-icdr-registration', 'title' => 'IMEI', 'kind' => 'certification']);

        (require database_path('migrations/2026_10_10_120000_publish_google_ads_landing_pages.php'))->up();

        $imei = LandingPage::where('path', 'lp/imei-certificate-for-import')->firstOrFail();
        $this->assertSame('published', $imei->status);
        $this->assertFalse($imei->indexable);
        $this->assertSame('certification/india/imei-icdr-registration', $imei->service_path);
        $this->assertCount(4, $imei->content['stats']);
        $this->assertNotEmpty($imei->content['hero']['reply_note']);
        $this->assertNull(LandingPage::where('path', 'lp/saber-certification')->value('service_path'));
        $this->assertSame(19, LandingPage::count());

        $bis = LandingPage::where('path', 'lp/bis-certification')->firstOrFail();
        $this->assertNotEmpty($bis->content['compare']['good']);
        $this->assertLessThanOrEqual(8, count($bis->content['faqs']['items']));
        $this->assertStringNotContainsString('9,999', json_encode($bis->content, JSON_UNESCAPED_UNICODE));
    }

    public function test_every_ad_page_saves_unchanged_in_the_editor(): void
    {
        Service::create(['path' => 'certification/india/imei-icdr-registration', 'slug' => 'imei-icdr-registration', 'title' => 'IMEI', 'kind' => 'certification']);
        Service::create(['path' => 'bis-certification', 'slug' => 'bis-certification', 'title' => 'BIS', 'kind' => 'certification']);
        (require database_path('migrations/2026_10_10_120000_publish_google_ads_landing_pages.php'))->up();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (LandingPage::all() as $page) {
            Livewire::test(EditLandingPage::class, ['record' => $page->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertSame($page->content['hero']['headline'], $page->fresh()->content['hero']['headline']);
        }
    }

    public function test_empty_rows_are_dropped_and_indexable_pages_keep_their_robots(): void
    {
        LandingPage::create([
            'title' => 'Test', 'path' => 'lp/test', 'status' => 'published', 'indexable' => true,
            'content' => ['hero' => ['headline' => 'Test', 'points' => ['One', '  ']], 'stats' => [], 'closing' => ['heading' => '']],
        ]);

        $this->getJson('/api/v1/landing-pages/lp/test')
            ->assertOk()
            ->assertJsonPath('data.content.hero.points', ['One'])
            ->assertJsonMissingPath('data.content.stats')
            ->assertJsonMissingPath('data.content.closing')
            ->assertJsonPath('data.seo.robots', 'index,follow');
    }

    public function test_uploaded_benefit_icons_are_served_as_urls(): void
    {
        LandingPage::create([
            'title' => 'Icons', 'path' => 'lp/icons', 'status' => 'published',
            'content' => ['benefits' => ['items' => [
                ['icon' => 'verified', 'iconImage' => 'icons/landing/2026/10/seal.png', 'title' => 'Approved'],
                ['icon' => 'bolt', 'title' => 'Fast'],
            ]]],
        ]);

        $items = $this->getJson('/api/v1/landing-pages/lp/icons')->assertOk()->json('data.content.benefits.items');

        $this->assertStringStartsWith('http', $items[0]['iconImage']);
        $this->assertStringEndsWith('/icons/landing/2026/10/seal.png', $items[0]['iconImage']);
        $this->assertArrayNotHasKey('iconImage', $items[1]);
    }

    public function test_section_photos_are_served_as_urls(): void
    {
        LandingPage::create([
            'title' => 'Photos', 'path' => 'lp/photos', 'status' => 'published',
            'content' => [
                'hero' => ['headline' => 'Photos', 'image' => 'landing/hero/phones.webp', 'image_alt' => 'Phones'],
                'intro' => ['heading' => 'What is it?', 'text' => 'Text', 'image' => 'landing/lab.webp', 'image_alt' => 'Lab test'],
                'benefits' => ['image' => 'landing/sections/desk.webp', 'image_alt' => 'Desk', 'items' => [['title' => 'Fast']]],
                'checklist' => ['image' => 'landing/sections/files.webp', 'image_alt' => 'Files', 'groups' => [['items' => ['Invoice']]]],
                'work' => ['photos' => [['image' => 'landing/work/site.webp', 'alt' => 'Site visit', 'caption' => 'Factory audit']]],
                'options' => ['items' => [['title' => 'ISI Mark', 'image' => 'landing/marks/isi-mark.webp', 'image_alt' => 'ISI mark specimen']]],
                'trust' => ['hide_logos' => true],
            ],
        ]);

        $content = $this->getJson('/api/v1/landing-pages/lp/photos')->assertOk()->json('data.content');

        $this->assertStringEndsWith('/landing/hero/phones.webp', $content['hero']['image']);
        $this->assertStringEndsWith('/landing/lab.webp', $content['intro']['image']);
        $this->assertStringEndsWith('/landing/sections/desk.webp', $content['benefits']['image']);
        $this->assertStringEndsWith('/landing/sections/files.webp', $content['checklist']['image']);
        $this->assertStringStartsWith('http', $content['work']['photos'][0]['image']);
        $this->assertStringEndsWith('/landing/marks/isi-mark.webp', $content['options']['items'][0]['image']);
        $this->assertTrue($content['trust']['hide_logos']);
    }

    public function test_the_shared_testimonials_list_serves_only_live_reviews(): void
    {
        Testimonial::create(['name' => 'Asha Rao', 'context' => 'BIS CRS', 'rating' => 5, 'quote' => 'Quick and clear.', 'is_published' => true]);
        Testimonial::create(['name' => 'Draft Person', 'context' => 'MSDS', 'rating' => 5, 'quote' => 'Not yet approved.', 'is_published' => false]);

        $response = $this->getJson('/api/v1/testimonials')->assertOk()
            ->assertJsonFragment(['name' => 'Asha Rao', 'context' => 'BIS CRS'])
            ->assertJsonMissing(['name' => 'Draft Person']);

        $this->assertStringContainsString('max-age=60', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_newly_added_review_is_listed_ahead_of_older_ones_in_the_same_position(): void
    {
        Testimonial::create(['name' => 'Older Review', 'rating' => 5, 'quote' => 'Added first.', 'is_published' => true]);
        Testimonial::create(['name' => 'Newer Review', 'rating' => 5, 'quote' => 'Added later.', 'is_published' => true]);

        $names = collect($this->getJson('/api/v1/testimonials')->assertOk()->json('data'))->pluck('name');

        $this->assertLessThan($names->search('Older Review'), $names->search('Newer Review'));
    }

    public function test_the_editor_checks_the_keyword_against_the_headline_and_title(): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->firstOrFail();
        $page->update(['seo' => ['keyword' => 'BIS certification', 'title' => 'Get ISI mark help fast']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditLandingPage::class, ['record' => $page->getKey()])
            ->assertSee('Headline (H1) contains “BIS certification”.')
            ->assertSee('Meta title doesn’t mention “BIS certification”.')
            ->assertSee('Preview in Google search');
    }

    public function test_a_landing_page_cannot_take_an_existing_url(): void
    {
        $this->seed(ContentSeeder::class);
        Page::create(['title' => 'About', 'path' => 'about', 'status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreateLandingPage::class)
            ->fillForm(['title' => 'Clash', 'path' => 'about', 'status' => 'draft', 'content' => ['hero' => ['headline' => 'Clash']]])
            ->call('create')
            ->assertHasFormErrors(['path']);

        Livewire::test(CreateLandingPage::class)
            ->fillForm(['title' => 'New', 'path' => 'lp/new-campaign', 'status' => 'published', 'content' => ['hero' => ['headline' => 'New']]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(LandingPage::where('path', 'lp/new-campaign')->exists());
    }

    public function test_leads_keep_the_ad_keyword_and_click_id(): void
    {
        $this->seed(ContentSeeder::class);

        $this->postJson('/api/v1/leads', [
            'type' => 'consultation', 'name' => 'Asha Verma', 'phone' => '+919876543210', 'consent' => true,
            'source_page' => '/lp/bis-certification', 'utm_term' => 'bis certification cost', 'gclid' => 'Cj0KCQ-test',
        ])->assertCreated();

        $this->assertDatabaseHas('leads', ['utm_term' => 'bis certification cost', 'gclid' => 'Cj0KCQ-test']);
    }
}
