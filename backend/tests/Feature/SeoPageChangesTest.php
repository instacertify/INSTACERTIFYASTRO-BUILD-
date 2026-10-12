<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoPageChangesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_12_180000_apply_seo_page_changes.php';

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    /** @return list<array<string, mixed>> */
    private function entries(): array
    {
        return array_merge(...array_map(fn (string $file) => require database_path("data/seo-updates/{$file}.php"), ['p1', 'p2', 'p3']));
    }

    private function blogPost(string $slug, string $content, array $faqs = []): Post
    {
        return Post::create([
            'type' => 'blog', 'title' => ucfirst($slug), 'slug' => $slug, 'content' => $content, 'faqs' => $faqs,
            'seo' => ['title' => 'Old title', 'keyword' => 'kept'], 'status' => 'published', 'published_at' => now()->subYear(),
        ]);
    }

    public function test_entries_are_well_formed_and_free_of_fee_claims(): void
    {
        $entries = $this->entries();
        $keys = array_map(fn (array $e) => "{$e['type']}:{$e['key']}", $entries);
        $this->assertSame($keys, array_unique($keys));

        foreach ($entries as $entry) {
            $this->assertContains($entry['type'], ['post', 'service']);
            if (isset($entry['title'])) {
                $this->assertLessThanOrEqual(60, mb_strlen($entry['title']), $entry['key']);
                $this->assertGreaterThanOrEqual(70, mb_strlen($entry['description']), $entry['key']);
                $this->assertLessThanOrEqual(160, mb_strlen($entry['description']), $entry['key']);
            }
            foreach ([...$entry['top'] ?? [], ...$entry['sections'] ?? [], ...$entry['faqs'] ?? []] as $pair) {
                $this->assertCount(2, $pair, $entry['key']);
            }

            $text = json_encode($entry, JSON_UNESCAPED_UNICODE);
            $this->assertDoesNotMatchRegularExpression('/9,999|professional fee|our fee|consultancy fee|FMCS/i', $text, $entry['key']);
            preg_match_all('/href="([^"]+)"/', $text, $links);
            foreach ($links[1] as $href) {
                $this->assertStringStartsWith('/', $href, "{$entry['key']} links off-site");
            }
        }
    }

    public function test_posts_get_answer_sections_and_faqs_without_losing_content(): void
    {
        Storage::fake('local');
        $rohs = $this->blogPost('what-is-rohs', '<p>Intro</p><h2>Origins of RoHS</h2><p>History</p><h2>FAQs: Your RoHS Questions Answered</h2><p>See below</p><h2>Stay RoHS Compliant: Partner with Instacertify</h2><p>Contact</p>', [
            ['q' => 'What does RoHS stand for?', 'a' => 'Restriction of Hazardous Substances.'],
            ['q' => 'What does RoHS stand for?', 'a' => 'Restriction of Hazardous Substances.'],
        ]);
        $duplicate = $this->blogPost('how-to-get-bee-certificate-in-india', '<p>Old BEE post</p>');

        $this->migration()->up();
        $this->migration()->up();

        $rohs->refresh();
        $this->assertSame('What Is RoHS? Restricted Substances, Limits & Compliance', $rohs->seo['title']);
        $this->assertSame($rohs->seo['title'], $rohs->seo['og_title']);
        $this->assertSame('kept', $rohs->seo['keyword']);
        $this->assertStringStartsWith('<p><strong>RoHS (Restriction of Hazardous Substances) limits 10 substances', $rohs->content);
        $this->assertSame(1, substr_count($rohs->content, '<h2>The 10 RoHS substances and limits</h2>'));
        $this->assertSame(1, substr_count($rohs->content, '<h2>RoHS declaration template</h2>'));
        $this->assertTrue(strpos($rohs->content, 'The 10 RoHS substances') < strpos($rohs->content, 'Origins of RoHS'));
        $this->assertTrue(strpos($rohs->content, 'RoHS declaration template') < strpos($rohs->content, 'FAQs: Your RoHS'));
        $this->assertStringContainsString('<p>History</p>', $rohs->content);
        $this->assertSame(
            ['What does RoHS stand for?', 'What does RoHS compliant mean?', 'Is RoHS mandatory in India?'],
            array_column($rohs->faqs, 'q'),
        );

        $duplicate->refresh();
        $this->assertSame('https://instacertify.com/resources/blogs/how-to-get-bee-certification-in-india', $duplicate->seo['canonical']);
        $this->assertSame('Old title', $duplicate->seo['title']);
        $this->assertSame('<p>Old BEE post</p>', $duplicate->content);
    }

    public function test_service_sections_land_in_the_right_tabs_and_corrections_apply(): void
    {
        Storage::fake('local');
        $faq = ['q' => 'Can I still use my existing CE certification in the UK?', 'a' => 'Yes.'];
        $ukca = Service::create([
            'path' => 'certification/emea/ukca-certification', 'slug' => 'ukca-certification', 'title' => 'UKCA Marking', 'kind' => 'certification',
            'detail' => [
                'lead' => 'Old lead',
                'sections' => [
                    ['label' => 'Overview', 'heading' => 'UKCA Ecosystem', 'html' => '<p>Admin text</p>'],
                    ['label' => 'Cost & Fees', 'heading' => 'Financial Planning: Costs & Fees', 'html' => '<p>Costs</p>'],
                ],
                'faqs' => [$faq, $faq],
            ],
        ]);
        $water = Service::create([
            'path' => 'services/testing/environment-water/packaged-drinking-water-testing', 'slug' => 'packaged-drinking-water-testing',
            'title' => 'Packaged Drinking Water Testing', 'kind' => 'testing', 'summary' => 'Old summary',
            'detail' => [
                'lead' => 'Test bottled and jar water against the BIS standards that packaged drinking water and natural mineral water must meet to be sold in India.',
                'body' => '<p>Both are under compulsory BIS certification, and FSSAI will grant a licence only once the BIS licence is in place. Other text.</p>',
                'faqs' => [['q' => 'Do I need BIS or FSSAI for a packaged water plant?', 'a' => 'Both.']],
            ],
        ]);
        $water->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
        $before = $water->fresh()->updated_at;

        $this->migration()->up();
        $this->migration()->up();

        $detail = $ukca->refresh()->detail;
        $this->assertStringStartsWith('UKCA (UK Conformity Assessed) is the product marking for Great Britain', $detail['lead']);
        $this->assertSame(
            ['UKCA vs CE', 'UKCA conformity assessment routes', 'UK declaration of conformity: what it must include', 'UKCA Ecosystem', 'Financial Planning: Costs & Fees'],
            array_column($detail['sections'], 'heading'),
        );
        $this->assertCount(5, $detail['faqs']);
        $this->assertSame($faq, $detail['faqs'][0]);

        $water->refresh();
        $this->assertStringContainsString('voluntary BIS certification', $water->summary);
        $this->assertStringContainsString('FSSAI’s Scheme of Testing', $water->detail['lead']);
        $this->assertStringContainsString('mandatory until 17 October 2024', $water->detail['body']);
        $this->assertStringContainsString('Other text.', $water->detail['body']);
        $this->assertStringStartsWith('FSSAI is required; BIS is now voluntary.', $water->detail['faqs'][0]['a']);
        $this->assertTrue($water->updated_at->greaterThan($before));
    }

    public function test_no_backup_is_written_when_none_of_the_pages_exist(): void
    {
        Storage::fake('local');

        $this->migration()->up();

        Storage::disk('local')->assertMissing('seo-backup-2026-10.json');
    }

    public function test_down_restores_the_original_pages(): void
    {
        Storage::fake('local');
        $post = $this->blogPost('what-is-rohs', '<p>Intro</p>', [['q' => 'Q?', 'a' => 'A.']]);
        $service = Service::create(['path' => 'cispr-32', 'slug' => 'cispr-32', 'title' => 'CISPR 32', 'kind' => 'testing', 'detail' => ['lead' => 'Old']]);
        $original = [$post->fresh()->only(['seo', 'content', 'faqs']), $service->fresh()->only(['seo', 'summary', 'detail'])];

        $this->migration()->up();
        $this->assertNotSame('Old', $service->fresh()->detail['lead']);
        Storage::disk('local')->assertExists('seo-backup-2026-10.json');

        $this->migration()->down();

        $this->assertSame($original, [$post->fresh()->only(['seo', 'content', 'faqs']), $service->fresh()->only(['seo', 'summary', 'detail'])]);
        Storage::disk('local')->assertMissing('seo-backup-2026-10.json');
    }
}
