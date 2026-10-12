<?php

use App\Models\LandingPage;
use App\Support\Images;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Pages that already have a photo at the top are left alone; the test suite doesn't copy files to the media disk. */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        foreach ($this->images() as $path => $alt) {
            $page = LandingPage::where('path', $path)->first();
            $name = str_replace('/', '-', $path);
            $file = database_path("data/landing-images/{$name}.jpg");
            if (! $page || filled($page->content['hero']['image'] ?? null) || ! is_file($file)) {
                continue;
            }

            $stored = "landing/hero/{$name}.webp";
            $content = $page->content ?? [];
            $content['hero'] = [
                ...($content['hero'] ?? []),
                'image' => Images::disk()->exists($stored) ? $stored : Images::store((string) file_get_contents($file), 'landing/hero', basename($file), 1600),
                'image_alt' => $alt,
            ];
            $page->update(['content' => $content]);
        }
    }

    public function down(): void
    {
        foreach ($this->images() as $path => $alt) {
            $page = LandingPage::where('path', $path)->first();
            if ($page && ($page->content['hero']['image_alt'] ?? null) === $alt && str_starts_with((string) ($page->content['hero']['image'] ?? ''), 'landing/hero/')) {
                $content = $page->content;
                Images::disk()->delete($content['hero']['image']);
                unset($content['hero']['image'], $content['hero']['image_alt']);
                $page->update(['content' => $content]);
            }
        }
    }

    /** @return array<string, string> */
    private function images(): array
    {
        return require database_path('data/landing-page-hero-images.php');
    }
};
