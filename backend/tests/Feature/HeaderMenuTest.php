<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHeaderMenu;
use App\Models\Setting;
use App\Models\User;
use App\Support\HeaderMenu;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_drops_hidden_and_incomplete_items_and_stops_at_three_levels(): void
    {
        $nav = HeaderMenu::forApi([
            ['label' => 'Services', 'href' => '/services', 'children' => [
                ['label' => 'Testing', 'href' => '/services/testing', 'description' => ' Labs ', 'children' => [
                    ['label' => 'Toys', 'href' => '/services/testing/toys-childcare', 'children' => [['label' => 'Too deep', 'href' => '/x']]],
                    ['label' => 'Hidden', 'href' => '/hidden', 'hidden' => true],
                ]],
                ['label' => '', 'href' => '/no-label'],
            ]],
            ['label' => 'Draft', 'href' => '/draft', 'hidden' => true],
        ]);

        $this->assertSame([[
            'label' => 'Services',
            'href' => '/services',
            'children' => [[
                'label' => 'Testing',
                'href' => '/services/testing',
                'description' => 'Labs',
                'children' => [['label' => 'Toys', 'href' => '/services/testing/toys-childcare']],
            ]],
        ]], $nav);
    }

    public function test_site_api_serves_the_cleaned_menu(): void
    {
        $this->seed(ContentSeeder::class);
        $setting = Setting::where('key', 'site')->first();
        $setting->value = [...$setting->value, 'nav' => [
            ['label' => 'Certifications', 'href' => '/certification', 'children' => [
                ['label' => 'India', 'href' => '/certification/india', 'children' => [['label' => 'BIS CRS', 'href' => '/certification/india/bis-crs-mark-certification']]],
            ]],
            ['label' => 'Old', 'href' => '/old', 'hidden' => true],
        ]];
        $setting->save();

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonCount(1, 'data.nav')
            ->assertJsonPath('data.nav.0.children.0.children.0.label', 'BIS CRS');
    }

    public function test_admin_can_edit_the_menu_without_touching_other_settings(): void
    {
        $this->seed(ContentSeeder::class);
        $before = Setting::get('site');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageHeaderMenu::class)
            ->set('data.nav', [
                ['label' => 'Testing', 'href' => '/services/testing', 'hidden' => false, 'children' => [
                    ['label' => 'Toys', 'href' => '/services/testing/toys-childcare', 'hidden' => false, 'children' => []],
                ]],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $site = Setting::where('key', 'site')->first()->value;
        $this->assertSame('Toys', $site['nav'][0]['children'][0]['label']);
        $this->assertSame($before['contact'], $site['contact']);
    }

    public function test_links_must_be_site_paths_or_web_addresses(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageHeaderMenu::class)
            ->set('data.nav', [['label' => 'Bad', 'href' => 'javascript:alert(1)', 'hidden' => false, 'children' => []]])
            ->call('save')
            ->assertHasErrors();
    }
}
