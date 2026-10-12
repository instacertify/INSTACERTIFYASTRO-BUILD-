<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoindexPolicyPagesTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_12_220000_noindex_policy_pages.php');
    }

    private function page(string $path, string $template, ?array $seo = null): Page
    {
        return Page::create(['path' => $path, 'title' => ucfirst($path), 'template' => $template, 'seo' => $seo, 'status' => 'published', 'published_at' => now()->subYear()]);
    }

    public function test_policy_pages_are_hidden_from_search_and_other_pages_are_not(): void
    {
        $privacy = $this->page('privacy-policy', 'policy', ['title' => 'Privacy Policy | Instacertify']);
        $terms = $this->page('terms-of-service', 'policy', ['robots' => 'index,nofollow']);
        $about = $this->page('about', 'about');

        $this->migration()->up();

        $this->assertSame(['title' => 'Privacy Policy | Instacertify', 'robots' => 'noindex,follow'], $privacy->fresh()->seo);
        $this->assertSame('index,nofollow', $terms->fresh()->seo['robots']);
        $this->assertNull($about->fresh()->seo);
        $this->getJson('/api/v1/pages/privacy-policy')->assertOk()->assertJsonPath('data.seo.robots', 'noindex,follow');

        $this->migration()->down();

        $this->assertSame(['title' => 'Privacy Policy | Instacertify'], $privacy->fresh()->seo);
        $this->assertSame('index,nofollow', $terms->fresh()->seo['robots']);
    }
}
