<?php

use App\Models\Setting;
use App\Support\HomePage;
use Illuminate\Database\Migrations\Migration;

/** "How it works" retold as identify, plan, approve, deliver; only steps still on an earlier default are replaced, so admin edits stay. */
return new class extends Migration
{
    private const HEADINGS = ['What happens after you send us your product details', 'From product details to certificate'];

    private const TEXTS = [
        'Send the product name, model and the countries you sell in. Within 24 hours we tell you the standard, the scheme and the documents you need.',
        'We book a BIS- or NABL-recognised lab, arrange your samples and prepare the technical file with you.',
        "We file on the regulator's portal and answer every query until the application is approved.",
        'You receive the licence and the marking rules. We remind you before it is due for renewal.',
        'Share your product and markets. Within 24 hours you get the standard, scheme and document list.',
        'We book a BIS- or NABL-recognised lab, arrange samples and prepare your technical file.',
        "We file on the regulator's portal and answer every query until approval.",
        'You get the licence and marking rules, and a reminder before renewal.',
    ];

    public function up(): void
    {
        $setting = Setting::find('home');
        $process = $setting?->value['process'] ?? null;
        $defaults = HomePage::defaults()['process'] ?? null;
        if (! is_array($process) || ! is_array($defaults)) {
            return;
        }

        $items = $process['items'] ?? [];
        $untouched = $items !== [] && collect($items)->every(fn ($step) => in_array($step['text'] ?? null, self::TEXTS, true));
        if (! $untouched) {
            return;
        }

        $process['items'] = $defaults['items'];
        $process['intro'] ??= $defaults['intro'] ?? null;
        if (in_array($process['heading'] ?? null, self::HEADINGS, true)) {
            $process['heading'] = $defaults['heading'];
        }

        $setting->update(['value' => ['process' => $process] + $setting->value]);
    }

    public function down(): void
    {
        // Copy edit; nothing to restore.
    }
};
