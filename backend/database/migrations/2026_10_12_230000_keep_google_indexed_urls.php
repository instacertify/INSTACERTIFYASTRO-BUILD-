<?php

use App\Models\Redirect;
use App\Models\Standard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Every address Google has indexed keeps answering with its page, without a redirect (database/data/legacy-urls.php):
 * library pages move to the URL the live site names as their main one, and older addresses show the same page.
 */
return new class extends Migration
{
    private const BACKUP = 'legacy-urls-backup-2026-10.json';

    public function up(): void
    {
        $data = require database_path('data/legacy-urls.php');
        $samePage = $data['same_page'];

        $columns = ['from_path', 'to_path', 'status_code', 'is_active'];
        $existing = Redirect::whereIn('from_path', array_keys($samePage))->get($columns)->toBase();
        $renamed = [];
        foreach ($data['rename'] as $path => $slug) {
            $standard = Standard::with('category')->firstWhere('path', $path);
            if (! $standard?->category || Standard::where('path', trim($standard->category->url(), '/').'/'.$slug)->exists()) {
                continue;
            }
            $standard->slug = $slug;
            $standard->save();
            $renamed[$path] = $slug;
            $samePage['/'.$path] = $standard->url();
        }
        $existing = $existing->merge(Redirect::whereIn('from_path', array_keys($samePage))->whereNotIn('from_path', $existing->pluck('from_path'))->get($columns)->toBase());

        if ($renamed || $existing->isNotEmpty()) {
            Storage::disk('local')->put(self::BACKUP, json_encode([
                'renamed' => $renamed,
                'redirects' => $existing->map(fn (Redirect $r) => $r->only($columns))->values(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $pages = Standard::pluck('path')->map(fn ($p) => '/'.$p)->flip();
        foreach ($samePage as $from => $to) {
            // An address that is itself a page stays that page.
            if (! isset($pages[$from])) {
                Redirect::updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => Redirect::SAME_PAGE, 'is_active' => true]);
            }
        }
    }

    public function down(): void
    {
        $disk = Storage::disk('local');
        $backup = $disk->exists(self::BACKUP) ? json_decode($disk->get(self::BACKUP), true) : ['renamed' => [], 'redirects' => []];
        $data = require database_path('data/legacy-urls.php');

        foreach ($backup['renamed'] as $path => $slug) {
            $standard = Standard::with('category')->firstWhere('path', dirname($path).'/'.$slug);
            if ($standard) {
                $standard->slug = basename($path);
                $standard->save();
            }
        }
        $froms = [...array_keys($data['same_page']), ...array_map(fn ($p) => '/'.$p, array_keys($backup['renamed']))];
        Redirect::whereIn('from_path', $froms)->where('status_code', Redirect::SAME_PAGE)->delete();
        foreach ($backup['redirects'] as $row) {
            Redirect::updateOrCreate(['from_path' => $row['from_path']], $row);
        }
    }
};
