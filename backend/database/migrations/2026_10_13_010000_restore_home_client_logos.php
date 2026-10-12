<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** The home page settings were first saved with an empty logo list, hiding the strip; the imported logos still sit in the site settings. */
    public function up(): void
    {
        $home = Setting::where('key', 'home')->first();
        $logos = collect(Setting::get('site', [])['clientLogos'] ?? [])->filter(fn ($logo) => is_array($logo) && filled($logo['image'] ?? null))->values()->all();
        if (! $home || $logos === [] || ! empty($home->value['clientLogos'])) {
            return;
        }

        $home->value = [...$home->value, 'clientLogos' => $logos];
        $home->save();
    }

    public function down(): void
    {
        //
    }
};
