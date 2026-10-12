<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\VideoBlock;
use App\Models\Post;
use App\Support\Images;
use App\Support\RichContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichContentBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function placeholder(string $id, array $config): string
    {
        return '<div data-type="customBlock" data-config="'.e(json_encode($config)).'" data-id="'.$id.'"></div>';
    }

    public function test_youtube_links_in_every_common_form_are_recognised(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame('dQw4w9WgXcQ', VideoBlock::parse($url)['id'] ?? null, $url);
        }

        $this->assertSame(90, VideoBlock::parse('https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s')['start']);
        $this->assertTrue(VideoBlock::parse('https://www.youtube.com/shorts/dQw4w9WgXcQ')['portrait']);
        $this->assertNull(VideoBlock::parse('https://vimeo.com/123456'));
        $this->assertNull(VideoBlock::parse('https://www.youtube.com/watch?v=short'));
    }

    public function test_blocks_render_at_the_chosen_size_and_other_html_is_left_alone(): void
    {
        $html = '<p class="legacy">Intro</p>'
            .$this->placeholder('video', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'How BIS works', 'size' => 'medium', 'align' => 'right', 'caption' => 'Two minutes'])
            .$this->placeholder('table', ['table' => '<table><tbody><tr><th><p>Fee</p></th></tr><tr><td><p>₹7,000</p></td></tr></tbody></table>', 'size' => 'small', 'align' => 'left'])
            .$this->placeholder('image', ['image' => 'content/images/2026/10/lab.webp', 'alt' => 'Lab bench', 'size' => 'full', 'align' => 'right'])
            .$this->placeholder('unknown', ['x' => 1]);

        $out = RichContent::render($html);

        $this->assertStringContainsString('<p class="legacy">Intro</p>', $out);
        $this->assertStringContainsString('class="embed embed-video size-medium align-right"', $out);
        $this->assertStringContainsString('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0"', $out);
        $this->assertStringContainsString('title="How BIS works"', $out);
        $this->assertStringContainsString('<figcaption>Two minutes</figcaption>', $out);
        $this->assertStringContainsString('class="embed embed-table size-small align-left"', $out);
        $this->assertStringContainsString('₹7,000', $out);
        $this->assertStringContainsString('class="embed embed-image size-full align-center"', $out);
        $this->assertStringContainsString('alt="Lab bench"', $out);
        $this->assertStringNotContainsString('customBlock', $out);
    }

    public function test_an_image_block_carries_its_dimensions_so_the_page_does_not_jump(): void
    {
        Storage::fake(config('instacertify.media_disk'));
        $image = imagecreatetruecolor(640, 360);
        ob_start();
        imagepng($image);
        Images::disk()->put('content/images/2026/10/size-test.png', ob_get_clean());

        $out = RichContent::render($this->placeholder('image', ['image' => 'content/images/2026/10/size-test.png', 'alt' => 'Test', 'size' => 'small', 'align' => 'left']));

        $this->assertStringContainsString('width="640" height="360"', $out);
        $this->assertStringContainsString('class="embed embed-image size-small align-left"', $out);
    }

    public function test_a_table_block_cannot_carry_scripts(): void
    {
        $out = RichContent::render($this->placeholder('table', ['table' => '<table><tbody><tr><td><p>ok</p><script>alert(1)</script></td></tr></tbody></table>']));

        $this->assertStringContainsString('ok', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    public function test_the_article_api_returns_the_rendered_blocks(): void
    {
        Post::create([
            'type' => 'blog', 'title' => 'Video guide', 'slug' => 'video-guide', 'status' => 'published', 'published_at' => now()->subDay(),
            'content' => '<p>Watch this.</p>'.$this->placeholder('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Guide', 'size' => 'small']),
        ]);

        $content = $this->getJson('/api/v1/posts/blog/video-guide')->assertOk()->json('data.content');

        $this->assertStringContainsString('embed-video size-small', $content);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $content);
    }
}
