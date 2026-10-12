<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageFooter;
use App\Models\Setting;
use App\Models\User;
use App\Support\FooterMenu;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FooterMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_drops_hidden_and_empty_items_and_fills_defaults(): void
    {
        $footer = FooterMenu::forApi([
            'columns' => [
                ['title' => ' Company ', 'links' => [
                    ['label' => 'About us', 'href' => '/about'],
                    ['label' => 'Old page', 'href' => '/old', 'hidden' => true],
                    ['label' => '', 'href' => '/no-label'],
                ]],
                ['title' => 'Draft', 'hidden' => true, 'links' => [['label' => 'X', 'href' => '/x']]],
                ['title' => 'Empty', 'links' => [['label' => 'Gone', 'href' => '/gone', 'hidden' => true]]],
            ],
            'portals' => [['label' => 'ERP login', 'href' => 'https://erp.example.com']],
        ]);

        $this->assertSame([['title' => 'Company', 'links' => [['label' => 'About us', 'href' => '/about']]]], $footer['columns']);
        $this->assertSame([['label' => 'ERP login', 'href' => 'https://erp.example.com']], $footer['portals']);
        $this->assertSame(FooterMenu::DEFAULTS['bottomLinks'], $footer['bottomLinks']);
        $this->assertSame('Regulatory updates', $footer['newsletter']['title']);
        $this->assertSame(FooterMenu::DEFAULTS['copyright'], $footer['copyright']);
    }

    public function test_switching_the_newsletter_off_removes_it_from_the_api(): void
    {
        $footer = FooterMenu::forApi(['newsletter' => ['show' => false, 'title' => 'Updates', 'text' => 'Monthly']]);

        $this->assertNull($footer['newsletter']);
    }

    public function test_site_api_serves_the_cleaned_footer(): void
    {
        $this->seed(ContentSeeder::class);
        $setting = Setting::where('key', 'site')->first();
        $setting->value = [...$setting->value, 'footer' => [
            'columns' => [['title' => 'Legal', 'links' => [['label' => 'Privacy policy', 'href' => '/privacy-policy'], ['label' => 'Draft', 'href' => '/draft', 'hidden' => true]]]],
            'portals' => [],
        ]];
        $setting->save();

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonCount(1, 'data.footer.columns')
            ->assertJsonCount(1, 'data.footer.columns.0.links')
            ->assertJsonPath('data.footer.bottomLinks.0.href', '/privacy-policy')
            ->assertJsonPath('data.footer.newsletter.title', 'Regulatory updates');
    }

    public function test_admin_can_edit_the_footer_without_touching_other_settings(): void
    {
        $this->seed(ContentSeeder::class);
        $before = Setting::get('site');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageFooter::class)
            ->assertSet('data.copyright', FooterMenu::DEFAULTS['copyright'])
            ->set('data.columns', [
                ['title' => 'Company', 'hidden' => false, 'links' => [['label' => 'Careers', 'href' => '/careers', 'hidden' => false]]],
            ])
            ->set('data.newsletter.show', false)
            ->set('data.copyright', '© {year} Instacertify Labs Pvt. Ltd.')
            ->call('save')
            ->assertHasNoErrors();

        $site = Setting::where('key', 'site')->first()->value;
        $this->assertSame('Careers', $site['footer']['columns'][0]['links'][0]['label']);
        $this->assertFalse($site['footer']['newsletter']['show']);
        $this->assertSame('Regulatory updates', $site['footer']['newsletter']['title']);
        $this->assertSame($before['nav'], $site['nav']);
        $this->assertSame($before['contact'], $site['contact']);
        $this->assertNull(FooterMenu::forApi($site['footer'])['newsletter']);
    }

    public function test_footer_links_must_be_site_paths_or_web_addresses(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageFooter::class)
            ->set('data.portals', [['label' => 'Bad', 'href' => 'javascript:alert(1)', 'hidden' => false]])
            ->call('save')
            ->assertHasErrors();
    }
}
