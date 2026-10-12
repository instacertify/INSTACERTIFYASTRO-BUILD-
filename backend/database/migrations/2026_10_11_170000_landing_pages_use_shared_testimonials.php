<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Landing pages show the website-wide testimonials. The per-page reviews seeded with the pages were sample
     * text, not client reviews, so they are dropped rather than moved into the testimonials.
     */
    public function up(): void
    {
        LandingPage::query()->each(function (LandingPage $page) {
            $content = $page->content ?? [];
            if (! array_key_exists('items', $content['reviews'] ?? [])) {
                return;
            }
            unset($content['reviews']['items']);
            $page->update(['content' => $content]);
        });
    }

    public function down(): void
    {
        //
    }
};
