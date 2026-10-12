<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    private const FINDER = [
        'enabled' => true,
        'heading' => 'Check the BIS fee for your product',
        'intro' => 'Search 1,900+ ISI and CRS products by product name, IS number or HSN code. See the BIS fee and when it became mandatory.',
        'suggestions' => ['LED lamp', 'Power bank', 'Pressure cooker', 'Helmet', 'Toys', 'Steel'],
        'crs_fee_micro' => 7000,
        'crs_fee_small' => 14500,
        'crs_fee_medium' => 27000,
        'crs_fee_large' => 27000,
        'crs_test_from' => 20000,
        'isi_other_charges' => 9000,
        'consulting_note' => 'Let’s explore your requirement',
    ];

    private const CONSULTING = 'Let’s explore your requirement';

    private const COST_FAQ = 'How much does BIS certification cost?';

    /** Seeded text that quoted consulting prices, and what replaces it. Edited text is left alone. */
    private const REPLACE = [
        'content.hero.price_note' => ['Consulting from ₹9,999 for CRS · ₹20,999 for ISI Mark', null],
        'content.options.items.0.price' => ['From ₹20,999', self::CONSULTING],
        'content.options.items.1.price' => ['From ₹9,999', self::CONSULTING],
        'seo.description' => [
            'ISI Mark and CRS registration handled end to end. Itemised quote naming the lab, testing charge and BIS fees. Consulting from ₹9,999.',
            'ISI Mark and CRS registration handled end to end. Search your product to see the BIS fee, then get an itemised quote naming the lab and testing charge.',
        ],
        'faq' => [
            'There are three parts: BIS’s own fees, lab testing and our fee. Consulting starts at ₹9,999 for CRS and ₹20,999 per product for ISI. Our quote names the lab and the testing charge, so nothing is left “at actual”.',
            'There are three parts: BIS’s own fees, lab testing and our fee. Search your product above to see its BIS fee – CRS testing starts at ₹20,000. Our fee depends on your product and factory, so let’s explore your requirement. The quote names the lab and the testing charge, so nothing is left “at actual”.',
        ],
    ];

    public function up(): void
    {
        $this->apply(fn (array $content) => $content + ['finder' => self::FINDER], 0, 1);
    }

    public function down(): void
    {
        $this->apply(fn (array $content) => Arr::except($content, 'finder'), 1, 0);
    }

    private function apply(Closure $finder, int $from, int $to): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->first();
        if (! $page) {
            return;
        }

        $data = ['content' => $finder($page->content ?? []), 'seo' => $page->seo ?? []];
        foreach (self::REPLACE as $key => $pair) {
            if ($key === 'faq') {
                foreach ($data['content']['faqs']['items'] ?? [] as $i => $item) {
                    if (($item['question'] ?? null) === self::COST_FAQ && ($item['answer'] ?? null) === $pair[$from]) {
                        $data['content']['faqs']['items'][$i]['answer'] = $pair[$to];
                    }
                }
            } elseif (data_get($data, $key) === $pair[$from] && $pair[$from] !== null) {
                $pair[$to] === null ? Arr::forget($data, $key) : data_set($data, $key, $pair[$to]);
            } elseif ($pair[$from] === null && data_get($data, $key) === null && $pair[$to] !== null) {
                data_set($data, $key, $pair[$to]);
            }
        }

        $page->update($data);
    }
};
