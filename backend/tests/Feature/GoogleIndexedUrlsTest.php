<?php

namespace Tests\Feature;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleIndexedUrlsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_12_230000_keep_google_indexed_urls.php');
    }

    public function test_same_page_entries_reach_the_build_and_need_a_page_on_this_site(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $create = fn (string $to) => Livewire::test(ManageRedirects::class)
            ->callAction('create', ['from_path' => '/old-address', 'to_path' => $to, 'status_code' => Redirect::SAME_PAGE, 'is_active' => true]);

        $create('https://www.bis.gov.in/')->assertHasActionErrors(['to_path']);
        $create('/new-address')->assertHasNoActionErrors();

        $this->assertSame(
            ['from' => '/old-address', 'to' => '/new-address', 'status' => Redirect::SAME_PAGE],
            collect($this->getJson('/api/v1/redirects')->assertOk()->json('data'))->firstWhere('from', '/old-address'),
        );
    }

    public function test_library_pages_move_to_their_live_main_url_and_old_addresses_keep_working(): void
    {
        Storage::fake('local');
        $this->migration()->down();
        $isi = StandardCategory::create(['title' => 'Mandatory BIS Standard', 'slug' => 'mandatory-bis-standard']);
        $batteries = Standard::create([
            'standard_category_id' => $isi->id, 'slug' => 'isi-mark-batteries', 'title' => 'ISI Mark for Batteries Manufacturer',
            'description' => '<p>Batteries.</p>', 'status' => 'published', 'published_at' => now()->subYear(),
        ]);
        $copper = Standard::create([
            'standard_category_id' => $isi->id, 'slug' => 'isi-mark-copper-products', 'title' => 'ISI Mark for Copper Products',
            'description' => '<p>Copper.</p>', 'status' => 'published', 'published_at' => now()->subYear(),
        ]);
        Redirect::create(['from_path' => '/certification/india/bis-isi-mark', 'to_path' => '/certification/india/bis-isi-mark-domestic-manufacturers']);

        $this->migration()->up();

        $this->assertSame('resources/standards/mandatory-bis-standard/isi-mark-for-batteries-manufacturer', $batteries->fresh()->path);
        $this->assertSame('resources/standards/mandatory-bis-standard/isi-mark-copper-products', $copper->fresh()->path);
        $rules = collect($this->getJson('/api/v1/redirects')->assertOk()->json('data'))->keyBy('from');
        $this->assertSame(
            ['from' => '/resources/standards/mandatory-bis-standard/isi-mark-batteries', 'to' => '/resources/standards/mandatory-bis-standard/isi-mark-for-batteries-manufacturer', 'status' => 200],
            $rules['/resources/standards/mandatory-bis-standard/isi-mark-batteries'],
        );
        $this->assertSame('/resources/standards/mandatory-bis-standard/isi-mark-copper-products', $rules['/resources/standards/mandatory-bis-standard/isi-mark-for-copper-products']['to']);
        $this->assertSame(200, $rules['/certification/india/bis-isi-mark']['status']);
        $this->assertSame(200, $rules['/services/ce-certification']['status']);

        $this->migration()->down();

        $this->assertSame('resources/standards/mandatory-bis-standard/isi-mark-batteries', $batteries->fresh()->path);
        $this->assertSame(301, Redirect::firstWhere('from_path', '/certification/india/bis-isi-mark')->status_code);
        $this->assertFalse(Redirect::where('status_code', Redirect::SAME_PAGE)->exists());
    }
}
