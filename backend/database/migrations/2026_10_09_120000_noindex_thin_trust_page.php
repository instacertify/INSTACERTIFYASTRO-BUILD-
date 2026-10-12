<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The imported /trust page is two sentences long, which search engines report as a soft 404.
     * It stays online; editors can make it indexable again from its SEO settings once it has content.
     */
    public function up(): void
    {
        $page = DB::table('pages')->where('path', 'trust')->first(['id', 'seo']);
        if (! $page) {
            return;
        }

        $seo = json_decode((string) $page->seo, true) ?: [];
        if (filled($seo['robots'] ?? null)) {
            return;
        }

        DB::table('pages')->where('id', $page->id)->update(['seo' => json_encode([...$seo, 'robots' => 'noindex, follow'])]);
    }

    public function down(): void {}
};
