<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    private const OLD_CONSULTING = 'Let’s explore your requirement';

    private const CONSULTING = 'Best-value quote – let’s talk';

    private const COST_FAQ = 'How much does BIS certification cost?';

    private const OLD_FAQ = 'There are three parts: BIS’s own fees, lab testing and our fee. Search your product above to see its BIS fee – CRS testing starts at ₹20,000. Our fee depends on your product and factory, so let’s explore your requirement. The quote names the lab and the testing charge, so nothing is left “at actual”.';

    private const FAQ = 'There are three parts: BIS’s own fees, lab testing and our fee. Search your product above to see its minimum marking fee. Every ISI mark product also has an application charge of ₹1,000, a licence fee of ₹1,000 and audit charges of ₹7,000. CRS testing starts at ₹20,000. Foreign manufacturers get a detailed quote. Our fee depends on your product and factory, so let’s talk and we will send our best-value quote. It names the lab and the testing charge, so nothing is left “at actual”.';

    /** The ISI "other BIS charges" total becomes its three parts. Edited text is left alone. */
    public function up(): void
    {
        $this->apply(function (array $content) {
            if (isset($content['finder']) && (int) ($content['finder']['isi_other_charges'] ?? 9000) === 9000) {
                $content['finder'] = Arr::except($content['finder'], 'isi_other_charges')
                    + ['isi_application_fee' => 1000, 'isi_licence_fee' => 1000, 'isi_audit_fee' => 7000];
            }

            return $content;
        }, [self::OLD_CONSULTING, self::OLD_FAQ], [self::CONSULTING, self::FAQ]);
    }

    public function down(): void
    {
        $this->apply(function (array $content) {
            if (isset($content['finder']['isi_application_fee'])) {
                $finder = $content['finder'];
                $content['finder'] = Arr::except($finder, ['isi_application_fee', 'isi_licence_fee', 'isi_audit_fee'])
                    + ['isi_other_charges' => (int) $finder['isi_application_fee'] + (int) ($finder['isi_licence_fee'] ?? 0) + (int) ($finder['isi_audit_fee'] ?? 0)];
            }

            return $content;
        }, [self::CONSULTING, self::FAQ], [self::OLD_CONSULTING, self::OLD_FAQ]);
    }

    /**
     * @param  array{0: string, 1: string}  $from  consulting line and cost answer to replace
     * @param  array{0: string, 1: string}  $to
     */
    private function apply(Closure $fees, array $from, array $to): void
    {
        $page = LandingPage::where('path', 'lp/bis-certification')->first();
        if (! $page) {
            return;
        }

        $content = $fees($page->content ?? []);
        if (($content['finder']['consulting_note'] ?? null) === $from[0]) {
            $content['finder']['consulting_note'] = $to[0];
        }
        foreach ($content['options']['items'] ?? [] as $i => $item) {
            if (($item['price'] ?? null) === $from[0]) {
                $content['options']['items'][$i]['price'] = $to[0];
            }
        }
        foreach ($content['faqs']['items'] ?? [] as $i => $item) {
            if (($item['question'] ?? null) === self::COST_FAQ && ($item['answer'] ?? null) === $from[1]) {
                $content['faqs']['items'][$i]['answer'] = $to[1];
            }
        }

        $page->update(['content' => $content]);
    }
};
