<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use App\Support\Discover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiscoverTest extends TestCase
{
    use RefreshDatabase;

    private function storeImage(string $path, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        Storage::disk(config('instacertify.media_disk'))->put($path, ob_get_clean());
        imagedestroy($image);

        return $path;
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create([
            'type' => 'news',
            'title' => 'BIS makes CRS registration mandatory for USB-C chargers from April',
            'slug' => 'bis-crs-usb-c-chargers',
            'excerpt' => 'Manufacturers and importers of USB-C chargers need a BIS CRS licence before selling in India from April.',
            'content' => '<p>'.str_repeat('word ', 450).'</p>',
            'image_alt' => 'Technician testing a USB-C charger in a lab',
            'status' => 'published',
            'published_at' => now()->subDay(),
            ...$attributes,
        ]);
    }

    public function test_headline_problems_are_detected(): void
    {
        $this->assertNull(Discover::headlineProblem('BIS makes CRS registration mandatory for USB-C chargers from April'));
        $this->assertStringContainsString('clickbait', Discover::headlineProblem('You won’t believe what BIS just did to charger imports'));
        $this->assertStringContainsString('clickbait', Discover::headlineProblem('This one simple trick gets your BIS licence approved faster'));
        $this->assertStringContainsString('ALL-CAPS', Discover::headlineProblem('BIS MAKES CRS MANDATORY FOR ALL CHARGERS'));
        $this->assertStringContainsString('"!"', Discover::headlineProblem('BIS makes CRS mandatory for all chargers!!'));
        $this->assertStringContainsString('Too short', Discover::headlineProblem('BIS update'));
        $this->assertStringContainsString('characters', Discover::headlineProblem(str_repeat('Compliance guide ', 8)));
    }

    public function test_well_formed_post_is_discover_ready_and_exposes_image_size(): void
    {
        Storage::fake(config('instacertify.media_disk'));
        $author = Author::create(['name' => 'Priya Sharma', 'slug' => 'priya-sharma']);

        $post = $this->makePost(['author_id' => $author->id, 'image' => $this->storeImage('posts/charger.png', 1600, 900)]);

        $this->assertSame([1600, 900], [$post->image_width, $post->image_height]);
        $this->assertTrue($post->discover_ready);
        $this->assertNull($post->discover_issues);

        $this->getJson('/api/v1/posts/news/bis-crs-usb-c-chargers')
            ->assertOk()
            ->assertJsonPath('data.imageWidth', 1600)
            ->assertJsonPath('data.imageHeight', 900);
    }

    public function test_small_image_and_missing_author_block_readiness(): void
    {
        Storage::fake(config('instacertify.media_disk'));

        $post = $this->makePost(['image' => $this->storeImage('posts/small.png', 800, 450)]);

        $this->assertFalse($post->discover_ready);
        $this->assertContains('Image at least 1200 px wide', $post->discover_issues);
        $this->assertContains('Author byline', $post->discover_issues);

        $post->update(['author_id' => Author::create(['name' => 'Pankaj Kumar', 'slug' => 'pankaj-kumar'])->id, 'image' => $this->storeImage('posts/large.png', 1920, 1080)]);

        $this->assertSame(1920, $post->image_width);
        $this->assertTrue($post->discover_ready);
    }

    public function test_admin_edit_page_shows_discover_checklist(): void
    {
        $post = $this->makePost();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get("/admin/posts/{$post->id}/edit")
            ->assertOk()
            ->assertSee('Google Discover')
            ->assertSee('Assign an author — Google expects clear authorship.');
    }
}
