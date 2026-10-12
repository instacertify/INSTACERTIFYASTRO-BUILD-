<?php

use App\Models\LandingPage;
use App\Support\Images;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Official BIS artwork: the ISI monogram from bis.gov.in and the Standard Mark from the CRS marking guidelines (Annexure II). */
    private const MARKS = [
        'isi' => [
            'file' => 'isi-mark.png',
            'alt' => 'ISI mark specimen',
            'caption' => 'The ISI mark as it appears on the product: the IS number goes above it and the BIS licence number (CM/L-…) below.',
        ],
        'crs' => [
            'file' => 'crs-standard-mark.png',
            'alt' => 'BIS Standard Mark for CRS registration, specimen',
            'caption' => 'The BIS Standard Mark for registered products: the IS number goes above it and the registration number (R-…) below.',
        ],
    ];

    /** Cards that already have a mark are left alone; the test suite doesn't copy files to the media disk. */
    public function up(): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->first();
        if (app()->runningUnitTests() || ! $page) {
            return;
        }

        $content = $page->content ?? [];
        foreach ($content['options']['items'] ?? [] as $i => $item) {
            $key = $this->markFor((string) ($item['title'] ?? ''));
            if (! $key || filled($item['image'] ?? null)) {
                continue;
            }
            $mark = self::MARKS[$key];
            $path = 'landing/marks/'.pathinfo($mark['file'], PATHINFO_FILENAME).'.webp';
            if (! Images::disk()->exists($path)) {
                $path = Images::store((string) file_get_contents(database_path("data/landing-images/marks/{$mark['file']}")), 'landing/marks', $mark['file'], 480);
            }
            $content['options']['items'][$i] = [...$item, 'image' => $path, 'image_alt' => $mark['alt'], 'image_caption' => $mark['caption']];
        }
        $page->update(['content' => $content]);
    }

    public function down(): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->first();
        if (! $page) {
            return;
        }

        $content = $page->content ?? [];
        $alts = array_column(self::MARKS, 'alt');
        foreach ($content['options']['items'] ?? [] as $i => $item) {
            if (in_array($item['image_alt'] ?? null, $alts, true)) {
                unset($content['options']['items'][$i]['image'], $content['options']['items'][$i]['image_alt'], $content['options']['items'][$i]['image_caption']);
            }
        }
        $page->update(['content' => $content]);
    }

    private function markFor(string $title): ?string
    {
        return match (true) {
            (bool) preg_match('/\bISI\b/i', $title) => 'isi',
            (bool) preg_match('/\bCRS\b|registration/i', $title) => 'crs',
            default => null,
        };
    }
};
