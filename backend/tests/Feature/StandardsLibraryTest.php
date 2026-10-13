<?php

namespace Tests\Feature;

use App\Filament\Resources\Standards\Pages\EditStandard;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\User;
use App\Support\Standards\LibraryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StandardsLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Standard $asbestos;

    private Standard $mobiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');

        $isi = StandardCategory::create(['title' => 'Mandatory BIS Standard', 'slug' => 'mandatory-bis-standard']);
        $crs = StandardCategory::create(['title' => 'Mandatory CRS Standards', 'slug' => 'mandatory-crs-standards']);
        StandardCategory::create(['title' => 'ISO Management System', 'slug' => 'iso-management-system']);

        $published = ['status' => 'published', 'published_at' => now()->subYear()];
        $this->asbestos = Standard::create([
            'standard_category_id' => $isi->id, 'title' => 'ISI Mark for Asbestos Products', 'slug' => 'isi-mark-asbestos',
            'products' => [['is_number' => '459:1992', 'name' => 'Asbestos sheets']], ...$published,
        ]);
        $this->mobiles = Standard::create([
            'standard_category_id' => $crs->id, 'title' => 'CRS Mark for Mobile Phones', 'slug' => 'crs-mark-mobile-phones',
            'products' => [['is_number' => 'IS 13252 (Part 1): 2010', 'name' => 'Mobile phones']], ...$published,
        ]);
    }

    private function import(bool $dryRun = false): LibraryImporter
    {
        return LibraryImporter::inTransaction(base_path('tests/Fixtures/standards'), false, $dryRun);
    }

    private function page(string $category, string $slug): Standard
    {
        return Standard::where('path', "resources/standards/{$category}/{$slug}")->firstOrFail();
    }

    public function test_existing_pages_keep_their_url_and_copy_and_gain_compliance_data(): void
    {
        $this->import();

        $asbestos = $this->asbestos->fresh();
        $this->assertSame('resources/standards/mandatory-bis-standard/isi-mark-asbestos', $asbestos->path);
        $this->assertSame('ISI Mark for Asbestos Products', $asbestos->title);
        $this->assertNull($asbestos->source);
        $this->assertSame('isi', $asbestos->scheme);
        $row = $asbestos->products[0];
        $this->assertSame(['459:1992', 'Asbestos sheets', 46000, 9200, '₹1.75 per tonne'], [$row['is_number'], $row['name'], $row['fee_large'], $row['fee_micro'], $row['unit_fee']]);
        // The sheet's "voluntary" default must not contradict a page we publish as mandatory.
        $this->assertArrayNotHasKey('status', $row);
        $this->assertSame('mandatory', $asbestos->regulation_status);

        $mobiles = $this->mobiles->fresh();
        $this->assertSame('resources/standards/mandatory-crs-standards/crs-mark-mobile-phones', $mobiles->path);
        $this->assertSame(['mandatory', '2014', 7000, 'Registration per model per factory'], [
            $mobiles->products[0]['status'], $mobiles->products[0]['since'], $mobiles->products[0]['fee_micro'], $mobiles->products[0]['note'],
        ]);
        $this->assertSame('mandatory', $mobiles->regulation_status);
        $this->assertSame('2014', $mobiles->mandatory_since);
        $this->assertSame('Electronics & IT Goods', $mobiles->sector);
    }

    public function test_missing_products_get_pages_in_the_right_section(): void
    {
        $this->import();

        $grill = Standard::where('is_keys', 'like', '% 302-p2-s9 %')->sole();
        $this->assertSame('library', $grill->source);
        $this->assertStringStartsWith('resources/standards/mandatory-bis-standard/isi-mark-', $grill->path);
        $this->assertSame(['mandatory', '1 Mar 2025', 15000, 40000, 2], [
            $grill->regulation_status, $grill->mandatory_since, $grill->products[0]['test_min'], $grill->products[0]['test_max'], $grill->products[0]['labs'],
        ]);
        $this->assertSame('₹17.30 per piece for the first 1,500; ₹10.40 per piece thereafter', $grill->products[0]['unit_fee']);

        $flag = Standard::where('is_keys', ' 1 ')->sole();
        $this->assertSame('voluntary-bis-standards', $flag->category->slug);
        $this->assertSame('voluntary', $flag->regulation_status);
        $this->assertStringContainsString('National Flag of India', $flag->title);

        $ropes = Standard::where('is_keys', ' 1084 ')->sole();
        $this->assertSame(['upcoming', '2027-06-05'], [$ropes->regulation_status, $ropes->mandatory_from->toDateString()]);
        $this->assertSame('mandatory', Standard::where('is_keys', ' 12795 ')->sole()->regulation_status, 'A QCO date already passed is mandatory.');

        $rings = $this->page('mandatory-crs-standards', 'crs-mark-smart-rings');
        $this->assertSame(['library', 'mandatory', '2026-01-01'], [$rings->source, $rings->regulation_status, $rings->mandatory_from->toDateString()]);
        $this->assertSame([35000, 'IS 13252 (Part 1):2010'], [$rings->products[0]['test_min'], $rings->products[0]['replaces']]);
        $this->assertSame(' 62368-p1 13252-p1 ', $rings->is_keys, 'Buyers still find the product by the superseded standard.');

        $ac = $this->page('bee-mandatory-star-labelling', 'bee-star-label-inverter-air-conditioner');
        $this->assertSame(['bee', 'mandatory', '2020'], [$ac->category->group, $ac->regulation_status, $ac->mandatory_since]);
        $this->assertContains('Testing charges', array_column($ac->facts, 'label'));
        $this->assertStringContainsString('Brand registration costs ₹50,000 for MSMEs', collect($ac->faqs)->firstWhere('q', 'What are the BEE registration fees?')['a']);
        $this->assertSame('voluntary', $this->page('bee-voluntary-star-labelling', 'bee-star-label-air-compressor')->regulation_status);
    }

    public function test_reimport_keeps_library_urls_and_admin_edits(): void
    {
        $this->import();
        $flag = Standard::where('is_keys', ' 1 ')->sole();
        $flag->update(['title' => 'Edited title']);
        $count = Standard::count();

        $this->import();

        $this->assertSame($count, Standard::count());
        $this->assertSame([$flag->path, 'Edited title'], [$flag->fresh()->path, $flag->fresh()->title]);
    }

    public function test_product_photos_survive_a_reimport_and_reach_the_api(): void
    {
        $this->import();
        $flag = Standard::where('is_keys', ' 1 ')->sole();
        $flag->update(['products' => [['image' => 'standards/products/flag.webp'] + $flag->products[0]]]);
        $this->asbestos->update(['products' => [['image' => 'standards/products/asbestos.webp'] + $this->asbestos->fresh()->products[0]]]);

        $this->import();

        $this->assertSame('standards/products/flag.webp', $flag->fresh()->products[0]['image']);
        $this->assertSame('standards/products/asbestos.webp', $this->asbestos->fresh()->products[0]['image']);
        $this->assertStringEndsWith('/storage/standards/products/flag.webp', $this->getJson("/api/v1/standards/{$flag->path}")->json('data.products.0.image'));
    }

    public function test_admin_uploads_a_page_image_that_survives_a_reimport_and_reaches_the_api(): void
    {
        Storage::fake(config('instacertify.media_disk'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditStandard::class, ['record' => $this->asbestos->getRouteKey()])
            ->fillForm(['image' => UploadedFile::fake()->image('asbestos sheet.png', 2400, 1800), 'image_alt' => 'Corrugated asbestos sheet'])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $this->asbestos->fresh()->image;
        $this->assertStringStartsWith('standards/pages/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk(config('instacertify.media_disk'))->assertExists($path);
        $this->assertSame(1600, getimagesizefromstring(Storage::disk(config('instacertify.media_disk'))->get($path))[0]);

        $this->import();

        $this->assertSame($path, $this->asbestos->fresh()->image);
        $this->getJson("/api/v1/standards/{$this->asbestos->path}")
            ->assertJsonPath('data.image', Storage::disk(config('instacertify.media_disk'))->url($path))
            ->assertJsonPath('data.imageAlt', 'Corrugated asbestos sheet');
        $this->assertArrayNotHasKey('image', $this->getJson('/api/v1/standards')->json('data.0.standards.0'));
    }

    public function test_dry_run_saves_nothing(): void
    {
        $importer = $this->import(dryRun: true);

        $this->assertGreaterThan(0, $importer->stats['isi pages created']);
        $this->assertSame(2, Standard::count());
        $this->assertNull($this->asbestos->fresh()->products[0]['fee_large'] ?? null);
    }

    public function test_upcoming_status_turns_mandatory_once_the_date_passes(): void
    {
        $this->assertSame('upcoming', Standard::inForce('upcoming', '2026-10-09'));
        $this->assertSame('mandatory', Standard::inForce('upcoming', '2026-10-08'));
        $this->assertSame('upcoming', Standard::inForce('upcoming', null));
        $this->assertSame('voluntary', Standard::inForce('voluntary', '2020-01-01'));

        $this->import();
        $this->travelTo('2027-06-05');

        $this->assertSame('mandatory', Standard::where('is_keys', ' 1084 ')->sole()->currentStatus());
    }

    public function test_api_exposes_groups_status_fees_and_facts(): void
    {
        $this->import();

        $categories = collect($this->getJson('/api/v1/standards')->assertOk()->json('data'));
        $bee = $categories->firstWhere('slug', 'bee-mandatory-star-labelling');
        $this->assertSame(['bee', 'BEE star labelling'], [$bee['group'], $bee['groupLabel']]);
        $mobiles = collect($categories->firstWhere('slug', 'mandatory-crs-standards')['standards'])->firstWhere('slug', 'crs-mark-mobile-phones');
        $this->assertSame(['mandatory', 'Mandatory', 7000, 27000], [$mobiles['regulationStatus'], $mobiles['statusLabel'], $mobiles['feeMin'], $mobiles['feeMax']]);
        $this->assertArrayNotHasKey('products', $mobiles);

        $this->getJson('/api/v1/standards/resources/standards/bee-mandatory-star-labelling/bee-star-label-inverter-air-conditioner')
            ->assertOk()
            ->assertJsonPath('data.regulationStatus', 'mandatory')
            ->assertJsonPath('data.products.0.test_min', 175000)
            ->assertJsonPath('data.facts.0.label', 'Status');
    }
}
