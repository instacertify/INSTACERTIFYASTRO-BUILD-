<?php

namespace App\Support\Standards;

use Illuminate\Support\Str;

/**
 * Copy for pages generated from the standards library; existing editorial pages never pass through here.
 * Written in plain English (CEFR B1): short sentences, everyday words, and any acronym explained the first time.
 */
class LibraryContent
{
    private const FMCS = 'Factories outside India apply under the Foreign Manufacturers Certification Scheme (FMCS). They must also name a representative in India, called an Authorised Indian Representative (AIR).';

    private const BEE_TIPS = [
        'Your test report must be less than one year old when you apply. It must come from an NABL-accredited lab.',
        'If BEE changes the star rating table, you must test the model again and register it again.',
        'If you do not pay the label fees, you can lose your security deposit. BEE also publishes the names of firms that do not pay.',
        'Labels must carry a QR code. You must also upload a list of your sellers on the BEE portal.',
        'Testing prices are a guide only. Ask the lab for a quote for your exact model.',
    ];

    public static function shorten(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max + 1);
        $cut = mb_substr($cut, 0, (int) mb_strrpos($cut, ' '));
        if (substr_count($cut, '(') > substr_count($cut, ')')) {
            $cut = mb_substr($cut, 0, (int) mb_strrpos($cut, '('));
        }

        return preg_replace('/\s+(?:and|or|of|for|the|with|to|in|on|by|a|an|-|–|\/|&)$/i', '', rtrim($cut, ' ,;:-–/(&'));
    }

    /** Name for a search-result title: a qualifier cut short ("Fittings for Potable") reads worse than none ("Fittings"). Not for slugs, which must stay stable. */
    public static function titleName(string $name, int $max): string
    {
        if (mb_strlen($name) <= $max) {
            return $name;
        }
        preg_match_all('/,|\s(?:for|with|other|used|in|on|not)\s|\s[-–]\s|\s\(/iu', $name, $m, PREG_OFFSET_CAPTURE);
        $breaks = array_values(array_filter(
            array_map(fn ($hit) => mb_strlen(substr($name, 0, $hit[1])), $m[0]),
            fn ($at) => $at >= 12,
        ));
        $fits = array_filter($breaks, fn ($at) => $at <= $max);
        // With no clean break, running a little long beats stopping mid-phrase.
        $at = $fits ? max($fits) : (mb_strlen($name) <= $max + 10 ? null : collect($breaks)->first(fn ($at) => $at <= $max + 10));
        if ($at === null) {
            return self::shorten($name, $max + 10);
        }

        return preg_replace('/\s+(?:for\s+use|and|or|of|the)$/i', '', rtrim(mb_substr($name, 0, $at), ' ,-–'));
    }

    /** Product name for questions and snippets: cutting it mid-phrase reads badly, so long names become "this product". */
    private static function askName(string $name): string
    {
        return mb_strlen($name) <= 60 ? $name : 'this product';
    }

    public static function isiSlug(string $name, string $key): string
    {
        $words = Str::slug(self::shorten($name, 60));

        return "isi-mark-{$words}-is-".str_replace(['-p', '-s'], ['-part-', '-sec-'], $key);
    }

    /** @return array{title: string, description: string, faqs: list<array{q: string, a: string}>, seo: array{title: string, description: string}, products_title: string} */
    public static function isi(string $name, string $is, array $f): array
    {
        $short = self::shorten($name, 90);
        $tiny = self::askName($name);
        $status = $f['status'] ?? 'voluntary';
        $since = $f['since'] ?? null;
        $phased = $since && str_starts_with($since, 'Phased');
        $start = $phased ? preg_replace('/^Phased from\s*/', '', $since) : $since;
        $order = $f['order'] ?? null;
        $note = $status === 'voluntary' ? ($f['note'] ?? null) : null;

        $intro = $note ? "The ISI mark for {$short} ({$is}) is now optional. {$note} Some makers keep their BIS licence, because government tenders and large buyers may still ask for it." : match ($status) {
            'mandatory' => "{$short} must carry the ISI mark. The Indian Standard for it is {$is}."
                .($since ? ($phased ? " The rule applies in stages, starting from {$start}." : " This has been the rule since {$since}.") : '')
                .($order ? " The legal order is: {$order}." : '')
                .' You cannot make, import, sell or store this product for sale in India without a valid BIS licence.',
            'upcoming' => "{$short} ({$is}) will soon need BIS certification".($since ? ", from {$since}" : '').'. After that date, you cannot make, import or sell it in India without a BIS licence and the ISI mark. Start testing now, so you are ready in time.',
            'deferred' => "The government has issued a Quality Control Order (QCO) for {$short} ({$is}). The start date is not fixed yet. When the order starts, the ISI mark will be compulsory. Apply early, because labs get busy close to the date.",
            'cdsco' => "CDSCO, the Indian regulator for medical devices, controls {$short} ({$is}) under the Medical Devices Rules, 2017. The ISI mark is optional. You can get it to show that your product meets the Indian Standard.",
            default => "There is no Quality Control Order (QCO) for {$short} today. This means the ISI mark for {$is} is optional. Many makers still get it, because government tenders, large buyers and online marketplaces often ask for it.",
        };

        $process = "How it works: a BIS-approved lab tests your samples against {$is}. A BIS officer then visits your factory. If everything is in order, BIS gives you a licence. You can then print the ISI mark with your licence number (CM/L number). ".self::FMCS;

        $money = [];
        if (isset($f['fee_large'])) {
            $money[] = 'Every year you pay BIS a minimum marking fee. It is ₹'.LibraryData::rupees($f['fee_micro'] ?? $f['fee_large']).' for micro firms and ₹'.LibraryData::rupees($f['fee_large']).' for large firms.'
                .(isset($f['unit_fee']) ? " The fee also depends on how much you make: {$f['unit_fee']}. You pay whichever amount is higher." : '');
        }
        if (isset($f['test_min'])) {
            $money[] = 'Lab testing costs about '.self::range($f['test_min'], $f['test_max']).' per sample, based on quotes from '.self::labCount($f['labs'] ?? 0).'.';
        }

        $html = '<p>'.e($intro).'</p><p>'.e($process).'</p>'.($money ? '<p>'.e(implode(' ', $money)).' These prices are a guide only and do not include GST.</p>' : '');

        $faqs = [['q' => "Is BIS certification mandatory for {$tiny}?", 'a' => $note ? "No, not any more. {$note}" : match ($status) {
            'mandatory' => "Yes. {$is} is mandatory".($since ? ($phased ? ", in stages starting from {$start}" : " since {$since}") : '').'. The product must carry the ISI mark and a valid licence number.',
            'upcoming' => 'Not yet. It will be mandatory'.($since ? " from {$since}" : ' soon').'. After that, you need the ISI mark to make, import or sell it in India.',
            'deferred' => 'Not yet. The government has issued the order, but the start date is not fixed. Until then, the ISI mark is optional. We follow the official notices and can tell you the latest position.',
            'cdsco' => 'Not from BIS. CDSCO controls this product under the Medical Devices Rules. The ISI mark is optional, but it helps to show quality.',
            default => 'No. There is no Quality Control Order for it today, so the ISI mark is optional. It can still help you win tenders and gain the trust of buyers.',
        }]];

        if (isset($f['fee_large'])) {
            $faqs[] = ['q' => "What is the BIS marking fee for {$tiny}?", 'a' => 'The minimum fee each year is ₹'.LibraryData::rupees($f['fee_large']).' for large firms'
                .(isset($f['fee_medium']) ? ', ₹'.LibraryData::rupees($f['fee_medium']).' for medium firms' : '')
                .(isset($f['fee_small']) ? ', ₹'.LibraryData::rupees($f['fee_small']).' for small firms' : '')
                .(isset($f['fee_micro']) ? ' and ₹'.LibraryData::rupees($f['fee_micro']).' for micro firms' : '').'. Your firm size comes from your Udyam registration. '
                .(isset($f['unit_fee']) ? "BIS also works out a fee at {$f['unit_fee']}. You pay the higher amount. " : '')
                .'GST is extra. These figures are a guide, based on the BIS fee list.'];
        }
        $faqs[] = ['q' => "How much does testing for {$is} cost?", 'a' => isset($f['test_min'])
            ? 'Lab charges are usually '.self::range($f['test_min'], $f['test_max']).' per sample. This is based on quotes from '.self::labCount($f['labs'] ?? 0).'. The final price depends on your model, its versions and the tests needed.'
            : 'The price depends on the lab, the number of models and the tests needed. Send us your product details and we will get quotes from BIS-approved labs.'];
        if (isset($f['hsn'])) {
            $faqs[] = ['q' => "What is the HSN code for {$tiny}?", 'a' => "It usually comes under HSN {$f['hsn']}. Check the code on your bill of entry, because customs decides the code for the exact product."];
        }
        $faqs[] = ['q' => 'Can a foreign manufacturer get the ISI mark?', 'a' => 'Yes. '.self::FMCS.' The licence is in the name of the factory. Once the product is mandatory, importers cannot clear the goods at customs without it.'];
        $faqs[] = ['q' => 'How long does BIS certification take?', 'a' => 'Factories in India usually get a licence 30 to 60 days after a good test report. Factories outside India (FMCS) should plan for four to six months, including the factory visit.'];

        $lead = match ($status) {
            'mandatory' => 'Mandatory'.($since && ! $phased ? " since {$since}" : ''),
            'upcoming' => 'Mandatory'.($since ? " from {$since}" : ' soon'),
            'deferred' => 'QCO issued',
            'cdsco' => 'Medical device',
            default => 'Voluntary',
        };

        return [
            'title' => "ISI Mark for {$short} – {$is}",
            'description' => $html,
            'faqs' => $faqs,
            'products_title' => 'Indian Standard covered',
            'seo' => [
                'title' => 'ISI Mark for '.self::titleName($name, max(18, 60 - 16 - mb_strlen($is)))." – {$is}",
                'description' => self::shorten("{$lead}: BIS certification for {$short} ({$is}). See the yearly fee by firm size, lab testing costs, HSN code and how to apply.", 158),
            ],
        ];
    }

    public static function crs(string $name, string $is, array $f): array
    {
        $since = $f['since'] ?? null;
        $tiny = self::askName($name);
        $upcoming = ($f['status'] ?? null) === 'upcoming';

        $intro = $upcoming
            ? ($since ? "From {$since}, you" : 'Soon, you')." must register {$name} with BIS under the Compulsory Registration Scheme (CRS). After that, each model must be registered and carry the Standard Mark before you sell or import it."
            : "You must register {$name} with BIS under the Compulsory Registration Scheme (CRS).".($since ? " This has been the rule since {$since}." : '')." Each model must first be tested to {$is} in a BIS-approved lab. You must register it before you sell or import it in India.";

        $process = 'How it works: a BIS-approved lab tests your model. You then apply to BIS with the test report. There is no factory visit before approval. BIS registers each model for each factory. The product must show the Standard Mark with your registration number (R-number). Makers outside India must name a representative in India, called an Authorised Indian Representative (AIR).';

        $faqs = [
            ['q' => "Is BIS registration mandatory for {$tiny}?", 'a' => $upcoming
                ? 'It will be'.($since ? " from {$since}" : ' soon').'. After that, each model needs CRS registration before you sell or import it.'
                : 'Yes. It is listed in the Electronics and IT Goods (Requirements for Compulsory Registration) Order. Each model needs a valid CRS registration and the Standard Mark.'],
            ['q' => 'What is the BIS fee for CRS registration?', 'a' => 'The BIS fee for each application is about ₹'.LibraryData::rupees($f['fee_micro'] ?? 7000).' for micro firms, ₹'.LibraryData::rupees($f['fee_small'] ?? 14500).' for small firms and ₹'.LibraryData::rupees($f['fee_large'] ?? 27000).' for medium and large firms. Lab testing and GST are extra.'],
            ['q' => "Which standard applies to {$tiny}?", 'a' => "The lab tests it to {$is}. The test report must be recent, and it must cover the exact model you want to register."],
            ['q' => 'How long does CRS registration take?', 'a' => 'Testing usually takes two to four weeks. After you send a complete application, BIS usually registers the model in 15 to 30 working days.'],
        ];

        return [
            'title' => "CRS Mark for {$name}",
            'description' => '<p>'.e($intro).'</p><p>'.e($process).'</p>',
            'faqs' => $faqs,
            'products_title' => 'Indian Standards covered',
            'seo' => [
                'title' => 'CRS Registration for '.self::titleName($name, max(18, 60 - 24 - mb_strlen($is)))." – {$is}",
                'description' => self::shorten("BIS CRS registration for {$name}: when it became mandatory, {$is} testing costs, BIS fees by firm size and how to apply.", 158),
            ],
        ];
    }

    public static function bee(array $p, bool $mandatory, array $fees): array
    {
        $name = preg_replace('/\s*;[^)]*\)/', ')', $p['product']);
        $tiny = self::shorten(preg_replace('/\s*\(.*$/', '', $name), 40);
        $regime = $p['regime'];
        [$min, $max, $per] = LibraryData::priceRange($p['testingPrice']);

        $intro = $mandatory
            ? "{$name} must have a star label from the Bureau of Energy Efficiency (BEE). BEE status: {$regime}. Every model sold in India must be registered with BEE. The label shows how much energy the model uses, based on lab tests."
            : "{$name} can have a star label from the Bureau of Energy Efficiency (BEE), but it is optional. BEE status: {$regime}. Brands use the label to show buyers that the product saves energy. It also helps in tenders.";
        $process = "How it works: an NABL-accredited lab tests each model to {$p['testStandard']}. Your brand registers with BEE first. Then you register each model with its test report. Every three months, you pay a fee for the labels you used.";

        $rows = collect($fees)->map(fn ($r) => '<tr><td>'.e($r['head']).'</td><td>'.e(self::rs($r['msme'])).'</td><td>'.e(self::rs($r['large'])).'</td><td>'.e($r['frequency']).'</td></tr>')->implode('');
        $table = '<h3>BEE fees</h3><table><thead><tr><th>Fee</th><th>MSME</th><th>Medium and large</th><th>When you pay</th></tr></thead><tbody>'.$rows.'</tbody></table>';
        $tips = '<h3>Good to know</h3><ul>'.collect(self::BEE_TIPS)->map(fn ($t) => '<li>'.e($t).'</li>')->implode('').'</ul>';

        $faqs = [
            ['q' => "Is the BEE star label mandatory for {$tiny}?", 'a' => $mandatory
                ? "Yes. BEE status: {$regime}. You cannot sell a model in India without a valid BEE label."
                : "No, it is optional. BEE status: {$regime}. But a star label helps buyers compare models, and tenders often ask for it."],
            ['q' => "What does BEE testing cost for {$tiny}?", 'a' => $min
                ? 'Lab charges are usually '.self::range($min, $max).($per ? " {$per}" : '').'.'
                : 'The price depends on the model and the lab. Ask us for a quote.'],
            ['q' => 'What are the BEE registration fees?', 'a' => self::beeRegistration($p['registrationFee']).' '.self::beeLabelFee($p['labelFee'])],
            ['q' => 'Which standard is used for testing?', 'a' => "Models are tested to {$p['testStandard']}. Star rating table: ".lcfirst((string) LibraryData::order($p['ratingTable'], 'mandatory')).'.'],
        ];

        return [
            'title' => "BEE Star Label for {$name}",
            'description' => '<p>'.e($intro).'</p><p>'.e($process).'</p>'.$table.$tips,
            'faqs' => $faqs,
            'products_title' => 'Test standards',
            'seo' => [
                'title' => mb_strlen($full = "BEE Star Label for {$tiny}: ".($mandatory ? 'Mandatory' : 'Voluntary').' Registration') <= 60
                    ? $full
                    : 'BEE Star Label for '.self::titleName($tiny, 41),
                'description' => self::shorten("BEE star rating for {$tiny}: {$regime}. See the test standard, lab testing costs, registration and label fees, and how to apply.", 158),
            ],
        ];
    }

    /** "Brand: Rs 50,000 (MSME) / Rs 3,00,000 (Med-Large); Model: Rs 300 (MSME) / Rs 5,000 (Large)" as plain sentences. */
    public static function beeRegistration(string $text): string
    {
        $money = '(?:Rs\.?\s*|₹)([\d,]+)';
        if (preg_match("/Brand:\\s*{$money}[^\\/]*\\/\\s*{$money}.*?Model:\\s*{$money}[^\\/]*\\/\\s*{$money}/i", $text, $m)) {
            return "Brand registration costs ₹{$m[1]} for MSMEs and ₹{$m[2]} for medium and large firms. Each model costs ₹{$m[3]} for MSMEs and ₹{$m[4]} for large firms.";
        }

        return self::rs($text).'.';
    }

    public static function beeLabelFee(string $text): string
    {
        if (blank($text) || str_starts_with(strtolower($text), 'per-label fee')) {
            return 'You also pay a fee for each label. The rate depends on the product. You pay it every three months, with sales figures checked by a chartered accountant.';
        }

        return 'You also pay a label fee of '.self::rs(preg_replace('/\s*\(.*\)$/', '', $text)).'.';
    }

    public static function rs(string $text): string
    {
        return preg_replace('/\bRs\.?\s*/', '₹', $text);
    }

    public static function range(int|float $min, int|float $max): string
    {
        return $min === $max ? '₹'.LibraryData::rupees($min) : '₹'.LibraryData::rupees($min).' – ₹'.LibraryData::rupees($max);
    }

    private static function labCount(int $labs): string
    {
        return $labs > 1 ? "{$labs} BIS-approved labs" : 'one BIS-approved lab';
    }
}
