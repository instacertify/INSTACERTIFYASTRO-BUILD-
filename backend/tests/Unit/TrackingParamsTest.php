<?php

namespace Tests\Unit;

use App\Support\TrackingParams;
use PHPUnit\Framework\TestCase;

class TrackingParamsTest extends TestCase
{
    public function test_removes_tracking_parameters_and_keeps_the_rest(): void
    {
        $this->assertSame('https://www.crsbis.in/BIS/about-crs.do', TrackingParams::clean('https://www.crsbis.in/BIS/about-crs.do?utm_source=chatgpt.com'));
        $this->assertSame('https://a.com/p?id=4#faq', TrackingParams::clean('https://a.com/p?utm_source=x&id=4&fbclid=abc#faq'));
        $this->assertSame('https://a.com/search?s=bis', TrackingParams::clean('https://a.com/search?s=bis'));
        $this->assertSame('/contact?utm_source=x', TrackingParams::clean('/contact?utm_source=x'));
    }

    public function test_cleans_links_inside_html_and_nested_arrays(): void
    {
        $html = '<p>See <a href="https://bis.gov.in/?lang=en&amp;utm_source=chatgpt.com&amp;utm_medium=referral" target="_blank">BIS</a>.</p>';

        $this->assertSame('<p>See <a href="https://bis.gov.in/?lang=en" target="_blank">BIS</a>.</p>', TrackingParams::clean($html));
        $this->assertSame(['blocks' => [['url' => 'https://a.com/x']], 'n' => 3], TrackingParams::clean(['blocks' => [['url' => 'https://a.com/x?gclid=1']], 'n' => 3]));
    }

    public function test_profile_links_also_lose_app_share_tags(): void
    {
        $this->assertSame('https://x.com/instacertify', TrackingParams::profileUrl('https://x.com/instacertify?s=11'));
        $this->assertSame('https://www.instagram.com/instacertify/', TrackingParams::profileUrl('https://www.instagram.com/instacertify/?igsh=MWx2&utm_source=qr'));
        $this->assertSame('https://www.facebook.com/profile.php?id=615', TrackingParams::profileUrl('https://www.facebook.com/profile.php?id=615'));
        $this->assertNull(TrackingParams::profileUrl(null));
    }
}
