<?php

use App\Models\Standard;
use App\Support\Standards\LibraryContent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Re-derives generated ISI search titles that ended on a dangling "Used"; titles an editor rewrote are kept. */
    public function up(): void
    {
        foreach (Standard::query()->get() as $standard) {
            $current = $standard->seo['title'] ?? null;
            if (! $current || ! preg_match('/^ISI Mark for (.+) – (IS .+)$/u', $standard->title, $m)
                || ! preg_match('/^ISI Mark for (.+) – IS /u', $current, $t) || ! str_starts_with($m[1], $t[1])) {
                continue;
            }
            $title = 'ISI Mark for '.LibraryContent::titleName($m[1], max(18, 60 - 16 - mb_strlen($m[2])))." – {$m[2]}";
            if ($title !== $current) {
                $standard->seo = ['title' => $title] + $standard->seo;
                $standard->save();
            }
        }
    }

    public function down(): void
    {
        //
    }
};
