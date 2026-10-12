<?php

use App\Models\LandingPage;
use App\Support\Images;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SECTIONS = ['intro', 'benefits', 'checklist'];

    /** Sections that already have a photo are left alone; the test suite doesn't copy files to the media disk. */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        ['images' => $images, 'pages' => $pages] = $this->data();
        $stored = [];
        $store = function (string $name) use (&$stored) {
            $path = "landing/sections/{$name}.webp";

            return $stored[$name] ??= Images::disk()->exists($path)
                ? $path
                : Images::store((string) file_get_contents(database_path("data/landing-images/sections/{$name}.jpg")), 'landing/sections', "{$name}.jpg", 1400);
        };

        foreach ($pages as $path => $names) {
            $page = LandingPage::where('path', $path)->first();
            if (! $page) {
                continue;
            }
            $content = $page->content ?? [];
            foreach (array_combine(self::SECTIONS, $names) as $section => $name) {
                if (filled($content[$section]['image'] ?? null)) {
                    continue;
                }
                $content[$section] = [...($content[$section] ?? []), 'image' => $store($name), 'image_alt' => $images[$name]];
            }
            $page->update(['content' => $content]);
        }
    }

    public function down(): void
    {
        ['images' => $images, 'pages' => $pages] = $this->data();
        foreach (array_keys($pages) as $path) {
            $page = LandingPage::where('path', $path)->first();
            if (! $page) {
                continue;
            }
            $content = $page->content ?? [];
            foreach (self::SECTIONS as $section) {
                if (str_starts_with((string) ($content[$section]['image'] ?? ''), 'landing/sections/') && in_array($content[$section]['image_alt'] ?? null, $images, true)) {
                    unset($content[$section]['image'], $content[$section]['image_alt']);
                }
            }
            $page->update(['content' => $content]);
        }
        foreach (array_keys($images) as $name) {
            Images::disk()->delete("landing/sections/{$name}.webp");
        }
    }

    /** @return array{images: array<string, string>, pages: array<string, list<string>>} */
    private function data(): array
    {
        return require database_path('data/landing-page-section-images.php');
    }
};
