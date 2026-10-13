<?php

namespace Tests\Feature;

use App\Filament\Components\IconPicker;
use App\Filament\Pages\ManageHomePage;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Support\HomePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_come_from_the_shared_content_file(): void
    {
        $home = HomePage::settings();

        $this->assertSame('Be tested. Be certified.', $home['hero']['title']);
        $this->assertFalse($home['hero']['showForm']);
        $this->assertSame(['Be tested', 'Be certified', 'Be audit-ready', 'Be unstoppable'], array_column($home['hero']['pillars'], 'title'));
        $this->assertSame('edit_note', $home['process']['items'][1]['icon']);
        $this->assertSame('Sample collection', $home['testingProcess']['items'][1]['title']);
        $this->assertCount(8, $home['explore']['items']);
    }

    public function test_untouched_lists_in_the_old_site_settings_give_way_to_the_defaults(): void
    {
        $original = json_decode((string) file_get_contents(config('instacertify.content_path').'/site.json'), true);
        Setting::create(['key' => 'site', 'value' => ['whyUs' => $original['whyUs'], 'process' => $original['process']]]);

        $home = HomePage::settings();

        $this->assertSame(HomePage::defaults()['whyUs']['items'], $home['whyUs']['items']);
        $this->assertSame(HomePage::defaults()['process']['items'], $home['process']['items']);
    }

    public function test_saved_steps_on_the_old_default_are_retold_and_edited_steps_are_kept(): void
    {
        $old = ['heading' => 'From product details to certificate', 'items' => [
            ['title' => 'Free assessment', 'text' => 'Share your product and markets. Within 24 hours you get the standard, scheme and document list.', 'icon' => 'support_agent'],
            ['title' => 'Certificate and renewals', 'text' => 'You get the licence and marking rules, and a reminder before renewal.', 'icon' => 'workspace_premium'],
        ]];
        Setting::create(['key' => 'home', 'value' => ['process' => $old, 'hero' => ['title' => 'Kept']]]);
        $migration = require database_path('migrations/2026_10_09_180000_retell_home_process_steps.php');

        $migration->up();

        $value = Setting::find('home')->value;
        $this->assertSame('From requirement to result', $value['process']['heading']);
        $this->assertSame(['Identify', 'Plan', 'Approve', 'Deliver'], array_column($value['process']['items'], 'title'));
        $this->assertSame('Kept', $value['hero']['title']);

        $edited = $old;
        $edited['items'][0]['text'] = 'Our own wording.';
        Setting::find('home')->update(['value' => ['process' => $edited]]);

        $migration->up();

        $this->assertSame($edited, Setting::find('home')->value['process']);
    }

    public function test_content_saved_in_the_old_site_settings_is_carried_over(): void
    {
        Setting::create(['key' => 'site', 'value' => [
            'whyUs' => [['title' => 'Old reason', 'text' => 'Kept']],
            'hero' => ['image' => 'hero/2026/10/photo.webp', 'overlay' => 'dark'],
            'clientLogosEnabled' => false,
        ]]);

        $home = HomePage::settings();

        $this->assertSame([['title' => 'Old reason', 'text' => 'Kept', 'icon' => 'engineering']], $home['whyUs']['items']);
        $this->assertSame('How we stop applications getting stuck', $home['whyUs']['heading']);
        $this->assertSame('dark', $home['hero']['overlay']);
        $this->assertSame('Get a free assessment', $home['hero']['buttonLabel']);
        $this->assertFalse($home['clientLogosEnabled']);
    }

    public function test_an_empty_logo_strip_gets_the_imported_logos_back_and_edited_strips_are_kept(): void
    {
        $logos = [['name' => 'Oppo', 'image' => 'logos/oppo.webp', 'url' => null], ['name' => 'No image', 'image' => null]];
        Setting::create(['key' => 'site', 'value' => ['clientLogos' => $logos]]);
        Setting::create(['key' => 'home', 'value' => ['clientLogos' => [], 'hero' => ['title' => 'Kept']]]);
        $migration = require database_path('migrations/2026_10_13_010000_restore_home_client_logos.php');

        $migration->up();

        $value = Setting::find('home')->value;
        $this->assertSame([$logos[0]], $value['clientLogos']);
        $this->assertSame('Kept', $value['hero']['title']);

        $own = [['name' => 'Chosen', 'image' => 'logos/chosen.webp']];
        Setting::find('home')->update(['value' => ['clientLogos' => $own]]);
        $migration->up();

        $this->assertSame($own, Setting::find('home')->value['clientLogos']);
    }

    public function test_lists_are_replaced_and_sections_merged(): void
    {
        $merged = HomePage::merge(
            ['faq' => ['heading' => 'Common questions', 'items' => [['q' => 'A'], ['q' => 'B']]]],
            ['faq' => ['items' => [['q' => 'Only']]]],
        );

        $this->assertSame(['heading' => 'Common questions', 'items' => [['q' => 'Only']]], $merged['faq']);
    }

    public function test_editors_can_edit_the_home_page_and_sales_cannot(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']));
        $this->get('/admin/home-page')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get('/admin/home-page')->assertOk()->assertSee('Home page');

        Livewire::test(ManageHomePage::class)
            ->set('data.hero.title', 'Certified for India, fast —')
            ->set('data.explore.items', [['title' => 'India', 'text' => 'BIS and more', 'href' => '/certification/india', 'icon' => 'flag']])
            ->set('data.faq.visible', false)
            ->call('save')
            ->assertHasNoErrors();

        $home = Setting::get('home');
        $this->assertSame('Certified for India, fast —', $home['hero']['title']);
        $this->assertSame('flag', $home['explore']['items'][0]['icon']);

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.home.hero.title', 'Certified for India, fast —')
            ->assertJsonPath('data.home.faq.visible', false)
            ->assertJsonPath('data.home.explore.items.0.icon', 'flag')
            ->assertJsonPath('data.process.0.icon', 'travel_explore')
            ->assertJsonMissingPath('data.hero');
    }

    public function test_hero_slide_photos_are_served_as_urls(): void
    {
        Setting::create(['key' => 'home', 'value' => ['hero' => ['pillars' => [
            ['title' => 'Be tested', 'text' => 'Lab work.', 'icon' => 'science', 'image' => 'hero/slides/2026/10/lab.webp', 'iconImage' => 'icons/home/2026/10/flask.svg'],
            ['title' => 'Be certified', 'icon' => 'verified'],
        ]]]]);

        $slides = $this->getJson('/api/v1/site')->json('data.home.hero.pillars');

        $this->assertStringEndsWith('/hero/slides/2026/10/lab.webp', $slides[0]['image']);
        $this->assertStringStartsWith('http', $slides[0]['image']);
        $this->assertStringEndsWith('/icons/home/2026/10/flask.svg', $slides[0]['iconImage']);
        $this->assertNull($slides[1]['image']);
        $this->assertNull($slides[1]['iconImage']);
    }

    public function test_what_we_do_tiles_are_edited_in_the_admin_and_served_by_the_api(): void
    {
        $this->assertSame(['/services/testing', '/certification', '/services/indian-registration-compliances', '/services/qualitiy'], array_column(HomePage::settings()['whatWeDo']['items'], 'href'));
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        Livewire::test(ManageHomePage::class)
            ->set('data.whatWeDo.heading', 'Our services')
            ->set('data.whatWeDo.items', [['title' => 'Inspection', 'text' => 'Pre-shipment checks.', 'href' => '/services/qualitiy', 'icon' => 'search']])
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.home.whatWeDo.heading', 'Our services')
            ->assertJsonPath('data.home.whatWeDo.items.0.title', 'Inspection')
            ->assertJsonPath('data.home.whatWeDo.items.0.iconImage', null);
    }

    public function test_home_events_and_latest_resources_are_chosen_in_the_admin(): void
    {
        $this->assertSame(['blog', 'news', 'update'], HomePage::settings()['latest']['types']);
        $this->assertSame('upcoming', HomePage::settings()['fieldwork']['eventsShow']);
        Event::create(['title' => 'Electronica', 'slug' => 'electronica', 'starts_at' => now()->addMonth(), 'status' => 'published']);
        Event::create(['title' => 'IFA', 'slug' => 'ifa', 'starts_at' => now()->subMonth(), 'status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        Livewire::test(ManageHomePage::class)
            ->set('data.fieldwork.eventsShow', 'picked')
            ->call('save')
            ->assertHasErrors(['data.fieldwork.events']);

        Livewire::test(ManageHomePage::class)
            ->set('data.fieldwork.eventsShow', 'picked')
            ->set('data.fieldwork.events', ['ifa', 'electronica'])
            ->set('data.latest.types', ['news', 'blog'])
            ->set('data.latest.perType', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.home.fieldwork.eventsShow', 'picked')
            ->assertJsonPath('data.home.fieldwork.events', ['ifa', 'electronica'])
            ->assertJsonPath('data.home.latest.types', ['news', 'blog'])
            ->assertJsonPath('data.home.latest.perType', 4);
        $this->assertNotNull($this->getJson('/api/v1/events')->json('data.0.publishedAt'));
    }

    public function test_unknown_icons_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageHomePage::class)
            ->set('data.stats.items', [['value' => '98%', 'label' => 'Approval rate', 'icon' => 'not_a_real_icon']])
            ->call('save')
            ->assertHasErrors();
    }

    public function test_icon_search_matches_words_and_ranks_exact_names_first(): void
    {
        $this->assertSame('verified', IconPicker::search('Verified')[0]);
        $this->assertContains('verified_user', IconPicker::search('verified user'));
        $this->assertSame([], IconPicker::search('  '));
    }
}
