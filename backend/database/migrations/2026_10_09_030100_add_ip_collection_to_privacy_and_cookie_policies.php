<?php

use App\Models\Page;
use App\Support\Privacy;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = Privacy::settings();
        $days = (int) $settings['ipDays'];
        $months = (int) $settings['consentLogMonths'];

        $enquiries = 'when you submit an enquiry, quote or call-back form on our website, we record the IP address of your device and your browser type together with the details you enter. We use them to block spam and fraudulent submissions and as a record of when and from where you agreed to be contacted (legitimate interest and legal obligation). Our team sees them alongside your enquiry, and they are deleted automatically after '.$days.' days.';
        $consents = 'When you make a choice in our cookie notice, we keep a consent record: a random consent ID, the cookie categories you allowed or refused, the policy version, the page and language, the date and time, your IP address and your browser type. We keep it to prove your choice, as required by the Digital Personal Data Protection Act, 2023 and the GDPR, and delete it, including the IP address, automatically after '.$months.' months. You can change or withdraw your choice at any time from Cookie settings at the bottom of every page; details are in our <a href="/cookie-policy">Cookie Policy</a>.';
        $retention = 'IP addresses recorded with enquiries are deleted after '.$days.' days and cookie consent records, including IP addresses, after '.$months.' months.';

        $this->patch('privacy-policy', 'IP address with enquiries', [
            ['Last Updated:</strong> 3 November 2025', 'Last Updated:</strong> 8 October 2026', null],
            ['internal CRM).', 'internal CRM). <strong>IP address with enquiries:</strong> '.$enquiries.' <strong>IP address with cookie choices:</strong> when you accept, reject, change or withdraw cookies in our cookie notice, we record your IP address with that choice (see section 8).', '<strong>IP address with enquiries:</strong> '.ucfirst($enquiries)],
            ['browser settings or opt-out tools.', 'browser settings or opt-out tools. '.$consents, '<strong>IP address with cookie choices:</strong> '.$consents],
            ['We retain data for five years.', 'We retain data for five years, except that '.$retention, ucfirst($retention)],
        ]);

        $proof = 'We use it only to prove your choice if it is ever questioned, for example by you or a regulator, and to detect misuse of the consent service. Consent records, including the IP address, are deleted automatically after '.$months.' months.';
        $forms = 'When you submit a form we also record your IP address and browser type, to block spam and fraudulent submissions and as a record of when and from where you gave consent. The IP address is deleted automatically after '.$days.' days.';

        $this->patch('cookie-policy', 'your IP address and your browser type', [
            [
                'your browser type and a one-way encrypted (hashed) version of your IP address. We cannot use it to identify you directly. Consent records are deleted automatically after the retention period set out in our Privacy Policy.',
                'the language, your IP address and your browser type. '.$proof,
                'Each consent record also holds the language, your IP address and your browser type. '.$proof,
            ],
            ['after which they are anonymised.</p>', 'after which they are anonymised. '.$forms.'</p>', $forms],
        ]);
    }

    public function down(): void
    {
        //
    }

    /** @param  list<array{0: string, 1: string, 2: ?string}>  $changes  [search, replace, paragraph to append if the search text was edited away] */
    private function patch(string $path, string $applied, array $changes): void
    {
        $page = Page::where('path', $path)->first();
        if (! $page || str_contains((string) $page->content, $applied)) {
            return;
        }

        $content = (string) $page->content;
        foreach ($changes as [$search, $replace, $fallback]) {
            if (str_contains($content, $search)) {
                $content = str_replace($search, $replace, $content);
            } elseif ($fallback) {
                $content .= "<p>{$fallback}</p>";
            }
        }

        $page->update(['content' => $content]);
    }
};
