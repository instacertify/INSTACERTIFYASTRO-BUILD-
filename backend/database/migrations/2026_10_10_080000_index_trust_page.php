<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The live site serves /trust as indexable; the noindex came from the old CMS record the
     * live frontend never used, so it is dropped to keep the page in Google.
     */
    public function up(): void
    {
        $page = Page::where('path', 'trust')->first();
        if (! $page || ! str_contains((string) ($page->seo['robots'] ?? ''), 'noindex')) {
            return;
        }
        $seo = $page->seo;
        unset($seo['robots']);
        $page->seo = $seo;
        $page->save();
    }

    public function down(): void
    {
        //
    }
};
