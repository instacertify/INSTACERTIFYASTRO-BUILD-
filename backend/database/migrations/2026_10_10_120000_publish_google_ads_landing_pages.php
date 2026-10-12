<?php

use App\Models\LandingPage;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const STATS = [
        ['value' => '42,818+', 'label' => 'Happy customers'],
        ['value' => '15+', 'label' => 'Expert advisors'],
        ['value' => '3', 'label' => 'Offices in India'],
    ];

    public function up(): void
    {
        $this->reviseBisPage();

        // Ad pages link to the imported service catalogue; a fresh install has none.
        if (! Service::exists()) {
            return;
        }

        foreach ($this->pages() as $page) {
            if (LandingPage::pathTaken($page['path'])) {
                continue;
            }

            $content = $page['content'];
            $content['stats'] = [...self::STATS, $page['stat']];
            $content['hero']['reply_note'] ??= 'We reply within 30 minutes in office hours';

            LandingPage::create([
                'title' => $page['title'],
                'path' => $page['path'],
                'status' => 'published',
                'published_at' => now(),
                'indexable' => false,
                'service_path' => Service::where('path', $page['service_path'])->exists() ? $page['service_path'] : null,
                'content' => $content,
                'seo' => $page['seo'],
            ]);
        }
    }

    public function down(): void
    {
        LandingPage::whereIn('path', array_column($this->pages(), 'path'))->delete();
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        return array_map(fn (string $file) => require $file, File::glob(database_path('data/landing-pages/*.php')));
    }

    /** Sharper keyword match and the new sections; the fee finder, stats, options and reviews stay as they are. */
    private function reviseBisPage(): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->first();
        if (! $page) {
            return;
        }

        $content = $page->content ?? [];
        $content['hero'] = array_merge($content['hero'] ?? [], [
            'subheadline' => 'Find out whether your product needs BIS, which route applies and what the BIS fee is – before you commit. Then we handle documents, lab testing and filing until the licence is granted.',
            'points' => [
                'BIS fee shown upfront – search your product below',
                'Itemised quote: lab, testing charge, BIS fees and our fee',
                'Indian manufacturers and foreign brands',
                'Offices in Noida, Vadodara and Kalyan',
            ],
            'price_note' => 'BIS fee for CRS from ₹7,000 – search your product for its exact fee',
            'cta' => 'Get my BIS quote',
        ]);
        $content['compare'] = [
            'heading' => '“At actual” is where BIS budgets go wrong',
            'intro' => 'Most BIS quotes leave the lab and testing charge “at actual” – the biggest cost, left open. Ours names the lab and the testing charge before you pay, and tells you honestly whether your product needs BIS at all.',
            'bad' => [
                'Testing charge left “at actual”',
                'Urgent certification sold for a QCO not yet in force',
                'Samples sent before the Indian Standard is confirmed',
                'The consultant disappears after the licence',
            ],
            'good' => [
                'Lab and testing charge named in the quote',
                'QCO status and dates checked first',
                'Scheme and Indian Standard confirmed before samples go',
                'Renewals, model additions and changes tracked',
            ],
        ];
        $content['checklist'] = [
            'heading' => 'Documents you’ll need',
            'intro' => 'Send what you have. We’ll give you a checklist for your exact product on the first call.',
            'groups' => [
                [
                    'title' => 'CRS registration (electronics & IT)',
                    'items' => [
                        'Company registration, GST and PAN',
                        'Brand or trademark details',
                        'Factory name, address and licence',
                        'Model list, photos and technical specifications',
                        'Critical components list',
                        'Authorised Indian Representative details, for foreign manufacturers',
                    ],
                ],
                [
                    'title' => 'ISI mark licence',
                    'items' => [
                        'Factory registration and licence',
                        'Manufacturing process flow chart',
                        'List of machinery and test equipment',
                        'In-house testing and quality control details',
                        'Site layout and location plan',
                        'Company registration, GST and PAN',
                    ],
                ],
            ],
        ];
        $content['related']['intro'] = 'Looking for BIS CRS registration, BIS registration for electronics, a BIS certificate for import or ISI mark certification? It’s the same team and the same product check.';
        $content['faqs']['items'] = [
            ...array_slice($content['faqs']['items'] ?? [], 0, 6),
            ['question' => 'Do I need BIS to import electronics into India?', 'answer' => 'Yes, if a Quality Control Order covers the product. Notified electronics and IT goods need CRS registration before they can be imported or sold, whether made in India or abroad, and customs checks the registration at import.'],
            ['question' => 'What does a BIS consultant actually do?', 'answer' => 'We confirm whether BIS applies, choose the scheme and Indian Standard, prepare the documents, book the BIS-recognised lab, file on the BIS portal and answer queries until the licence is granted. You can apply yourself – what you pay us for is getting it right first time.'],
        ];
        $content['closing']['heading'] = 'Find out where your product stands';

        $page->update(['content' => $content]);
    }
};
