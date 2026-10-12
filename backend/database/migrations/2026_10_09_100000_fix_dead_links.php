<?php

use App\Models\Setting;
use App\Support\Legacy\HtmlCleaner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')->where('content', 'like', '%cdn-cgi/l/email-protection%')->get(['id', 'content'])
            ->each(fn (object $page) => DB::table('pages')->where('id', $page->id)
                ->update(['content' => HtmlCleaner::restoreEmails($page->content)]));

        // "Client login" pointed at /portal, which has never existed on either site.
        $setting = Setting::where('key', 'site')->first();
        $portals = $setting?->value['footer']['portals'] ?? null;
        if ($portals) {
            $value = $setting->value;
            $value['footer']['portals'] = array_values(array_filter($portals, fn (array $link) => ($link['href'] ?? null) !== '/portal'));
            $setting->value = $value;
            $setting->save();
        }
    }

    public function down(): void {}
};
