<?php

namespace Tests\Unit;

use App\Support\Legacy\HtmlCleaner;
use PHPUnit\Framework\TestCase;

class HtmlCleanerTest extends TestCase
{
    private function clean(string $html): ?string
    {
        return (new HtmlCleaner(['instacertify.com'], fn ($src) => str_replace('https://instacertify.com/', '/media/', $src)))->clean($html);
    }

    public function test_strips_editor_noise_and_keeps_semantics(): void
    {
        $html = "<div class=\"x\" data-rocket=\"1\"><p data-path-to-node=\"4\" style=\"color:red\">IEC <b data-index-in-node=\"2\">62133</b>&nbsp;test</p>\r\n\t<p></p><span>loose</span></div>";

        $this->assertSame('<p>IEC <strong>62133</strong> test</p>loose', $this->clean($html));
    }

    public function test_spaces_between_inline_tags_are_kept(): void
    {
        $this->assertSame('<p><strong>BIS</strong> <em>CRS</em></p>', $this->clean('<p><b>BIS</b>  <i>CRS</i></p>'));
    }

    public function test_internal_links_become_relative_and_external_open_safely(): void
    {
        $out = $this->clean('<p><a href="https://www.instacertify.com/certification/india/bis/" target="_blank">BIS</a> <a href="https://bis.gov.in">BIS site</a></p>');

        $this->assertStringContainsString('<a href="/certification/india/bis">BIS</a>', $out);
        $this->assertStringContainsString('<a href="https://bis.gov.in" target="_blank" rel="noopener">', $out);
    }

    public function test_images_are_rewritten_and_scripts_removed(): void
    {
        $out = $this->clean('<h1>Title</h1><p><img src="https://instacertify.com/a.png" class="big"></p><script>alert(1)</script><iframe src="https://evil.test"></iframe>');

        $this->assertSame('<h2>Title</h2><p><img src="/media/a.png" loading="lazy" alt=""></p>', $out);
    }

    public function test_plain_text_is_wrapped_in_paragraphs(): void
    {
        $this->assertSame('<p>First</p><p>Second</p>', $this->clean("First\n\nSecond"));
    }
}
