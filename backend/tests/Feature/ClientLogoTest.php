<?php

namespace Tests\Feature;

use App\Support\Images;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientLogoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('instacertify.media_disk'));
    }

    /** @param  array{0: int, 1: int, 2: int, 3: int}  $background  RGBA, alpha 0–127 */
    private function logo(array $background): string
    {
        $image = imagecreatetruecolor(400, 300);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, ...$background));
        imagefilledrectangle($image, 100, 120, 299, 159, imagecolorallocate($image, 20, 60, 120));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function pixels(string $path): array
    {
        return array_slice(getimagesizefromstring(Images::disk()->get($path)), 0, 2);
    }

    public function test_transparent_and_white_margins_are_trimmed(): void
    {
        $this->assertSame([200, 40], $this->pixels(Images::storeLogo($this->logo([0, 0, 0, 127]), 'logos', 'clear.png')));
        $this->assertSame([200, 40], $this->pixels(Images::storeLogo($this->logo([255, 255, 255, 0]), 'logos', 'white.png')));
    }

    public function test_tile_icons_lose_their_empty_margins_and_are_stored_at_icon_size(): void
    {
        $this->assertSame([200, 40], $this->pixels(Images::storeIcon($this->logo([0, 0, 0, 127]), 'icons/home', 'clear.png')));
        $this->assertSame([200, 40], $this->pixels(Images::storeIcon($this->logo([255, 255, 255, 0]), 'icons/home', 'white.png')));
        $this->assertSame([256, 192], $this->pixels(Images::storeIcon($this->logo([215, 21, 23, 0]), 'icons/home', 'red.png')));
    }

    public function test_logos_on_a_coloured_background_are_kept_whole(): void
    {
        // Not cropped, only scaled down to the 160 px height logos are stored at.
        $this->assertSame([213, 160], $this->pixels(Images::storeLogo($this->logo([215, 21, 23, 0]), 'logos', 'red.png')));
    }
}
