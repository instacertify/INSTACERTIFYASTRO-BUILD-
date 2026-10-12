<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legal and policy pages stay linked from the footer for visitors and ad reviewers, but are kept out of search results.
     * They are not blocked in robots.txt: Google has to crawl them to see the noindex. Editors can undo it per page in SEO settings.
     */
    public function up(): void
    {
        foreach (DB::table('pages')->where('template', 'policy')->get(['id', 'seo']) as $page) {
            $seo = json_decode((string) $page->seo, true) ?: [];
            if (filled($seo['robots'] ?? null)) {
                continue;
            }
            DB::table('pages')->where('id', $page->id)->update(['seo' => json_encode([...$seo, 'robots' => 'noindex,follow'])]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('pages')->where('template', 'policy')->get(['id', 'seo']) as $page) {
            $seo = json_decode((string) $page->seo, true) ?: [];
            if (($seo['robots'] ?? null) !== 'noindex,follow') {
                continue;
            }
            unset($seo['robots']);
            DB::table('pages')->where('id', $page->id)->update(['seo' => $seo ? json_encode($seo) : null]);
        }
    }
};
