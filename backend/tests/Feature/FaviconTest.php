<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSite;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentSeeder::class);
        Storage::fake(config('instacertify.media_disk'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_admin_can_upload_a_favicon_used_by_the_website_and_admin_panel(): void
    {
        $this->get('/admin')->assertSee(asset('favicon.png'), false);
        $this->getJson('/api/v1/site')->assertJsonPath('data.favicon', null);

        Livewire::test(ManageSite::class)
            ->fillForm(['favicon' => UploadedFile::fake()->image('logo.png', 800, 600)])
            ->call('save')
            ->assertHasNoErrors();

        $path = Setting::get('site')['favicon'];
        $this->assertMatchesRegularExpression('#^branding/favicon-[a-z0-9]{8}\.png$#', $path);
        $disk = Storage::disk(config('instacertify.media_disk'));
        $disk->assertExists($path);
        $this->assertSame([512, 512], array_slice(getimagesizefromstring($disk->get($path)), 0, 2));

        $url = $disk->url($path);
        $this->getJson('/api/v1/site')->assertJsonPath('data.favicon', $url);
        $this->get('/admin')->assertSee($url, false);
    }

    public function test_replacing_the_favicon_removes_the_old_file(): void
    {
        Livewire::test(ManageSite::class)
            ->fillForm(['favicon' => UploadedFile::fake()->image('one.png', 600, 600)])
            ->call('save');
        $first = Setting::get('site')['favicon'];

        Livewire::test(ManageSite::class)
            ->set('data.favicon', [])
            ->fillForm(['favicon' => UploadedFile::fake()->image('two.png', 600, 600)])
            ->call('save')
            ->assertHasNoErrors();

        $disk = Storage::disk(config('instacertify.media_disk'));
        $second = Setting::get('site')['favicon'];
        $this->assertNotSame($first, $second);
        $disk->assertMissing($first);
        $disk->assertExists($second);

        Livewire::test(ManageSite::class)
            ->set('data.favicon', [])
            ->call('save')
            ->assertHasNoErrors();

        $disk->assertMissing($second);
        $this->getJson('/api/v1/site')->assertJsonPath('data.favicon', null);
    }

    public function test_transparent_edges_are_trimmed_and_wide_logos_are_fitted_not_cropped(): void
    {
        $canvas = imagecreatetruecolor(300, 336);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagefilledrectangle($canvas, 20, 40, 219, 139, imagecolorallocate($canvas, 200, 50, 50));
        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        Livewire::test(ManageSite::class)
            ->fillForm(['favicon' => UploadedFile::fake()->createWithContent('logo.png', $png)])
            ->call('save')
            ->assertHasNoErrors();

        $stored = imagecreatefromstring(Storage::disk(config('instacertify.media_disk'))->get(Setting::get('site')['favicon']));
        $this->assertSame([200, 200], [imagesx($stored), imagesy($stored)]);
        $alpha = fn (int $x, int $y) => imagecolorat($stored, $x, $y) >> 24 & 0x7F;
        $this->assertSame(0, $alpha(0, 100), 'left edge of the logo is kept');
        $this->assertSame(0, $alpha(199, 100), 'right edge of the logo is kept');
        $this->assertSame(127, $alpha(100, 10), 'padding above the logo is transparent');
    }

    public function test_svg_and_other_files_are_rejected(): void
    {
        Livewire::test(ManageSite::class)
            ->fillForm(['favicon' => UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->call('save')
            ->assertHasErrors(['data.favicon']);

        $this->assertArrayNotHasKey('favicon', Setting::get('site') ?? []);
    }
}
