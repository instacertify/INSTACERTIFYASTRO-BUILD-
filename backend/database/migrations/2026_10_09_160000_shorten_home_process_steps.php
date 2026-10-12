<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** Shorter "How it works" copy; only text still matching the original default is replaced, so admin edits stay. */
return new class extends Migration
{
    private const HEADING = ['What happens after you send us your product details' => 'From product details to certificate'];

    private const STEPS = [
        'Send the product name, model and the countries you sell in. Within 24 hours we tell you the standard, the scheme and the documents you need.' => ['Free assessment', 'Share your product and markets. Within 24 hours you get the standard, scheme and document list.'],
        'We book a BIS- or NABL-recognised lab, arrange your samples and prepare the technical file with you.' => ['Testing and documents', 'We book a BIS- or NABL-recognised lab, arrange samples and prepare your technical file.'],
        "We file on the regulator's portal and answer every query until the application is approved." => ['Filing and approval', "We file on the regulator's portal and answer every query until approval."],
        'You receive the licence and the marking rules. We remind you before it is due for renewal.' => ['Certificate and renewals', 'You get the licence and marking rules, and a reminder before renewal.'],
    ];

    public function up(): void
    {
        $setting = Setting::find('home');
        $process = $setting?->value['process'] ?? null;
        if (! is_array($process)) {
            return;
        }

        $process['heading'] = self::HEADING[$process['heading'] ?? ''] ?? ($process['heading'] ?? null);
        $process['items'] = array_map(function (array $step) {
            [$title, $text] = self::STEPS[$step['text'] ?? ''] ?? [$step['title'] ?? null, $step['text'] ?? null];

            return ['title' => $title, 'text' => $text] + $step;
        }, $process['items'] ?? []);

        $setting->update(['value' => ['process' => $process] + $setting->value]);
    }

    public function down(): void
    {
        // Copy edit; nothing to restore.
    }
};
