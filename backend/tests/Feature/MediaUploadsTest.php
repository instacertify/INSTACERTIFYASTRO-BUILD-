<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHomePage;
use App\Filament\Pages\ManageSite;
use App\Models\Industry;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\Images;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentSeeder::class);
        Storage::fake(config('instacertify.media_disk'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function url(string $path): string
    {
        return Storage::disk(config('instacertify.media_disk'))->url($path);
    }

    public function test_uploads_with_the_same_name_do_not_overwrite_each_other_and_svgs_are_cleaned(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)"><path d="M0 0h1v1z"/></a></svg>';

        $first = Images::store($svg, 'icons', 'icon.svg');
        $second = Images::store($svg, 'icons', 'icon.svg');

        $this->assertSame('icons/icon.svg', $first);
        $this->assertMatchesRegularExpression('#^icons/icon-[a-z0-9]{6}\.svg$#', $second);
        $stored = Storage::disk(config('instacertify.media_disk'))->get($first);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringContainsString('<path d="M0 0h1v1z"/>', $stored);
    }

    public function test_tricky_svgs_lose_every_way_to_run_code(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"/onload=alert(1)><a href="&#106;avascript:alert(2)"><rect width="1" height="1"/></a>'
            .'<animate attributeName="href" values="javascript:alert(3)"/><iframe src="https://example.com"></iframe>'
            .'<image href="data:text/html;base64,PHNjcmlwdD4="/><image href="data:image/png;base64,iVBORw0KGgo="/></svg>';

        $stored = Storage::disk(config('instacertify.media_disk'))->get(Images::store($svg, 'icons', 'tricky.svg'));

        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('&#106;', $stored);
        $this->assertStringNotContainsString('javascript', $stored);
        $this->assertStringNotContainsString('<iframe', $stored);
        $this->assertStringNotContainsString('data:text/html', $stored);
        $this->assertStringContainsString('data:image/png', $stored, 'Embedded pictures stay');
        $this->assertStringContainsString('<rect width="1" height="1"/>', $stored);
    }

    public function test_a_file_is_saved_with_the_extension_of_what_it_is_not_what_it_is_called(): void
    {
        $disk = Storage::disk(config('instacertify.media_disk'));
        $gifWithCode = "GIF89a\x01\x00\x01\x00\x00\x00\x00;<?php system(\$_GET['c']); ?>";

        $gif = Images::store($gifWithCode, 'uploads', 'shell.php');
        $this->assertSame('uploads/shell.gif', $gif);

        $svg = Images::store('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>', 'uploads', 'logo.phtml');
        $this->assertSame('uploads/logo.svg', $svg);

        $other = Images::store('<?php echo 1;', 'uploads', 'note.php');
        $this->assertStringEndsWith('.bin', $other);
        $this->assertSame([], array_filter($disk->allFiles('uploads'), fn (string $file) => str_contains($file, '.php')));
    }

    public function test_logo_and_testing_industry_icons_are_uploaded_in_site_settings(): void
    {
        Livewire::test(ManageSite::class)
            ->fillForm([
                'logo' => UploadedFile::fake()->image('logo.png', 420, 72),
                'testCategories' => ['food-beverages' => ['icon' => 'eco'], 'chemicals' => ['iconImage' => UploadedFile::fake()->image('flask.png', 64, 64)]],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $site = Setting::get('site');
        $this->assertStringStartsWith('branding/', $site['logo']);
        $this->assertStringEndsWith('.webp', $site['testCategories']['chemicals']['iconImage']);

        $response = $this->getJson('/api/v1/site')->assertOk()
            ->assertJsonPath('data.logo', $this->url($site['logo']))
            ->assertJsonMissingPath('data.testCategories');
        $categories = collect($response->json('data.taxonomy.testCategories'))->keyBy('slug');
        $this->assertSame(['eco', null], [$categories['food-beverages']['icon'], $categories['food-beverages']['iconImage']]);
        $this->assertSame($this->url($site['testCategories']['chemicals']['iconImage']), $categories['chemicals']['iconImage']);
        $this->assertSame('medical_services', $categories['medical-healthcare']['icon']);
    }

    public function test_popup_side_image_is_uploaded_served_and_replaced(): void
    {
        $this->getJson('/api/v1/site')->assertJsonPath('data.popup.image', null);

        Livewire::test(ManageSite::class)
            ->fillForm(['popup' => ['enabled' => true, 'image' => UploadedFile::fake()->image('team.jpg', 900, 1200), 'imageAlt' => 'Our Noida lab', 'imageFocus' => 'left']])
            ->call('save')
            ->assertHasNoErrors();

        $first = Setting::get('site')['popup']['image'];
        $this->assertStringStartsWith('popup/', $first);
        $this->assertStringEndsWith('.webp', $first);
        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.popup.image', $this->url($first))
            ->assertJsonPath('data.popup.imageAlt', 'Our Noida lab')
            ->assertJsonPath('data.popup.imageFocus', 'left')
            ->assertJsonPath('data.popup.enabled', true);

        Livewire::test(ManageSite::class)
            ->set('data.popup.image', null)
            ->set('data.popup.image', [UploadedFile::fake()->image('office.png', 800, 1000)])
            ->call('save')
            ->assertHasNoErrors();

        $second = Setting::get('site')['popup']['image'];
        $this->assertNotSame($first, $second);
        Storage::disk(config('instacertify.media_disk'))->assertMissing($first)->assertExists($second);

        Livewire::test(ManageSite::class)->set('data.popup.image', null)->call('save')->assertHasNoErrors();

        Storage::disk(config('instacertify.media_disk'))->assertMissing($second);
        $this->getJson('/api/v1/site')->assertJsonPath('data.popup.image', null);
        $this->get('/admin/manage-site?tab=popup')->assertOk()->assertSee('Side image');
    }

    public function test_home_page_tiles_take_an_uploaded_icon(): void
    {
        $page = Livewire::test(ManageHomePage::class);
        $first = array_key_first($page->get('data.whyUs.items'));

        $page->set("data.whyUs.items.{$first}.iconImage", [UploadedFile::fake()->image('badge.png', 128, 128)])
            ->call('save')
            ->assertHasNoErrors();

        $path = Setting::get('home')['whyUs']['items'][0]['iconImage'];
        $this->assertStringStartsWith('icons/home/', $path);
        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.home.whyUs.items.0.iconImage', $this->url($path))
            ->assertJsonPath('data.home.whyUs.items.1.iconImage', null);
    }

    public function test_industry_icons_and_social_images_reach_the_api(): void
    {
        $industry = Industry::firstOrFail();
        $industry->update(['icon' => 'memory', 'icon_image' => 'icons/industries/chip.svg', 'seo' => ['image' => 'social/share.webp']]);

        $this->getJson("/api/v1/industries/{$industry->slug}")
            ->assertOk()
            ->assertJsonPath('data.iconImage', $this->url('icons/industries/chip.svg'))
            ->assertJsonPath('data.seo.image', $this->url('social/share.webp'));

        $service = Service::where('path', 'certification/india/bis-crs-mark-certification')->sole();
        $service->update(['seo' => ['image' => 'https://cdn.example.com/share.png']]);
        $this->getJson('/api/v1/services/certification/india/bis-crs-mark-certification')
            ->assertJsonPath('data.seo.image', 'https://cdn.example.com/share.png');

        $this->get("/admin/industries/{$industry->id}/edit")->assertOk()->assertSee('Or upload your own icon');
        $this->get('/admin/manage-site?tab=testing-industries')->assertOk();
    }
}
