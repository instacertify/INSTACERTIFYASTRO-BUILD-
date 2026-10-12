<?php

namespace App\Support\Standards;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Turns the raw spreadsheet wording into the clean values shown on the website. */
class LibraryData
{
    private const ACRONYMS = [
        'AC', 'DC', 'PVC', 'UPVC', 'CPVC', 'HDPE', 'LDPE', 'LLDPE', 'PP', 'PE', 'PET', 'ABS', 'BOPP', 'EPDM', 'SBR', 'LPG', 'PNG',
        'CNG', 'LED', 'CFL', 'GLS', 'LCD', 'TV', 'USB', 'MCB', 'RCCB', 'ELCB', 'XLPE', 'ACSR', 'AAC', 'AAAC', 'PCC', 'RCC', 'OPC',
        'PPC', 'FRP', 'GRP', 'GI', 'MS', 'CI', 'DI', 'ERW', 'TMT', 'HSD', 'IS', 'ISO', 'IEC', 'UV', 'NPK', 'DAP', 'SSP', 'MOP',
        'EC', 'II', 'III', 'IV', 'VI', 'VII', 'VIII', 'IX', 'XI', 'XII', 'RO', 'UPS', 'CCTV', 'EV', 'BLDC', 'MDF', 'HDF', 'MEG',
        'PTA', 'VCM', 'VAM', 'DMF', 'TDI', 'MDI', 'PU', 'PC', 'PS', 'PMMA', 'SAN', 'NBR', 'CR', 'IIR', 'BR', 'NR', 'FCC', 'X-RAY',
    ];

    private const SMALL_WORDS = ['a', 'an', 'and', 'as', 'at', 'by', 'for', 'from', 'in', 'into', 'of', 'on', 'or', 'the', 'to', 'with', 'without', 'per', 'other', 'than', 'its', 'their'];

    /** @return array{0: string, 1: string} [status key, sheet label] */
    public static function status(?string $label): array
    {
        $l = strtolower((string) $label);

        return [match (true) {
            str_contains($l, 'rescinded') => 'rescinded',
            str_contains($l, 'cdsco') => 'cdsco',
            str_contains($l, 'upcoming mandatory') => 'upcoming',
            str_contains($l, 'notified') => 'deferred',
            str_starts_with($l, 'mandatory') => 'mandatory',
            default => 'voluntary',
        }, (string) $label];
    }

    /** @return array{0: ?string, 1: ?string} [Y-m-d date or null, display label or null] */
    public static function since(?string $text): array
    {
        $text = (string) $text;
        $phased = str_contains(strtolower($text), 'phased');
        if (preg_match('/^(?:mandatory|voluntary)(?: since)? ((?:19|20)\d\d)\b/i', $text, $m)) {
            return [null, $m[1]];
        }

        $dated = '(\d{1,2})[-.](\d{1,2})[-.]((?:19|20)\d\d)\b';
        if ((preg_match("/\\beff(?:ective|\\.)?\\s*(?:from\\s*)?{$dated}/i", $text, $m) || preg_match("/\\b{$dated}/", $text, $m))
            && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $date = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]);

            return [$date->toDateString(), ($phased ? 'Phased from ' : '').$date->format('j M Y')];
        }
        if (preg_match('/\b(\d{1,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* ((?:19|20)\d\d)\b/', $text, $m)) {
            $date = Carbon::createFromFormat('j M Y', "{$m[1]} {$m[2]} {$m[3]}");

            return [$date->toDateString(), $date->format('j M Y')];
        }
        if (preg_match('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*[- ]((?:19|20)\d\d)\b/', $text, $m)) {
            $date = Carbon::createFromFormat('j M Y', "1 {$m[1]} {$m[2]}");

            return [$date->toDateString(), ($phased ? 'Phased from ' : '').$date->format('M Y')];
        }
        if (preg_match('/\b(?:since|effective|phased|mandatory|voluntary)\b\D{0,20}?((?:19|20)\d\d)\b/i', $text, $m)
            || preg_match('/\b(?:Order|Act|Rules|QCO)\s*,?\s*((?:19|20)\d\d)\b/', $text, $m)) {
            return [null, ($phased ? 'Phased from ' : '').$m[1]];
        }

        return [null, null];
    }

    /** Order wording without the compiler's internal "verify" notes and source tags. */
    public static function order(?string $text, string $status): ?string
    {
        if ($status === 'voluntary' || blank($text)) {
            return null;
        }
        $text = preg_replace('/\s*-\s*BIS Upcoming QCO list \([^)]*\)/i', '', $text);
        $text = preg_replace('/\s*\((?:[^()]*\b)?verify\b[^()]*\)/i', '', $text);
        $text = preg_replace('/\s*[-;,]\s*(?:in force )?\(?verify\b[^;]*$/i', '', $text);
        $text = preg_replace('/;\s*(?:in force|notified)\s*$/i', '', trim($text));
        $text = preg_replace('/[;,]\s*(?:in force\s+)?since\s+[\d\-.]+\s*$/i', '', $text);

        return trim($text, ' ;,-') ?: null;
    }

    /** "₹17.30 per piece for the first 1,500; ₹10.40 per piece thereafter" */
    public static function unitFee(?string $unit, array $slabs): ?string
    {
        $slabs = array_values(array_filter($slabs, fn ($s) => is_numeric($s[0] ?? null)));
        if (! $unit || ! $slabs) {
            return null;
        }
        $unit = self::unit($unit);
        if (! $unit) {
            return null;
        }
        $per = fn ($rate) => '₹'.self::rupees($rate, 2)." per {$unit}";

        if (count($slabs) === 1) {
            return $per($slabs[0][0]);
        }

        $parts = [];
        foreach ($slabs as $i => [$rate, $qty]) {
            $qty = is_numeric($qty) ? number_format((float) $qty) : null;
            $parts[] = $per($rate).match (true) {
                $i === 0 && $qty !== null => " for the first {$qty}",
                $qty !== null => " for the next {$qty}",
                default => ' thereafter',
            };
        }

        return implode('; ', $parts);
    }

    /** "100 PCS" → "100 pieces", "1 M.T." → "tonne", "ONE KILO LITRE" → "kilolitre" */
    public static function unit(string $raw): ?string
    {
        $raw = preg_replace('/^per\s+/', '', strtolower(trim($raw)));
        if (! preg_match('/^(one|i(?= )|\d[\d,]*)?\s*([a-z].*)$/', $raw, $m)) {
            return null;
        }
        $qty = in_array($m[1], ['', 'one', 'i'], true) ? 1 : (int) str_replace(',', '', $m[1]);
        $noun = trim(str_replace('.', '', preg_replace('/\s*[(\/,=].*$/', '', $m[2])));
        [$singular, $plural] = match (true) {
            (bool) preg_match('/^(?:pieces?|pcs?|nos?|numbers?)$/', $noun) => ['piece', 'pieces'],
            (bool) preg_match('/^(?:m\s?t|tonnes?|tons?|tone|metric tonnes?)$/', $noun) => ['tonne', 'tonnes'],
            (bool) preg_match('/^(?:kgs?|kilo(?:gram)?s?)$/', $noun) => ['kg', 'kg'],
            (bool) preg_match('/^(?:kl|kilo ?lit(?:re|er)s?)$/', $noun) => ['kilolitre', 'kilolitres'],
            (bool) preg_match('/^(?:lit(?:re|er)s?|lts?|ltrs?|l)$/', $noun) => ['litre', 'litres'],
            (bool) preg_match('/^(?:sq\s?m(?:etre|eter|trs?|ts?)?s?|square met(?:re|er)s?)$/', $noun) => ['sq m', 'sq m'],
            (bool) preg_match('/^(?:cub\s?m(?:tr|etre|eter)?s?|cubic met(?:re|er)s?)$/', $noun) => ['cubic metre', 'cubic metres'],
            (bool) preg_match('/^(?:(?:linear |running )?(?:m|mtrs?|met(?:re|er)s?)|rm)$/', $noun) => ['metre', 'metres'],
            (bool) preg_match('/^pairs?$/', $noun) => ['pair', 'pairs'],
            (bool) preg_match('/^kw$/', $noun) => ['kW', 'kW'],
            (bool) preg_match('/^kva$/', $noun) => ['kVA', 'kVA'],
            (bool) preg_match('/^unit$/', $noun) => ['unit', 'units'],
            (bool) preg_match('/verify|^-$/', $noun) => [null, null],
            default => [$noun, $noun],
        };
        if (! $singular) {
            return null;
        }

        return $qty === 1 ? $singular : number_format($qty).' '.$plural;
    }

    /** @param  list<array{lab: string, city: ?string, price: int|float|null}>  $entries */
    public static function testing(array $entries): ?array
    {
        $priced = collect($entries)->filter(fn ($e) => is_numeric($e['price'] ?? null) && $e['price'] >= 500);
        if ($priced->isEmpty()) {
            return null;
        }
        $labs = $priced->sortBy('price')->pluck('lab')->map(fn ($l) => Str::of($l)->squish()->limit(70)->toString())->unique()->values();

        return [
            'test_min' => (int) $priced->min('price'),
            'test_max' => (int) $priced->max('price'),
            'labs' => $labs->count(),
            'lab_names' => $labs->take(5)->implode('; '),
        ];
    }

    /** "HOT ROLLED CARBON STEEL SHEET AND STRIP - Specification (Second Revision)" → "Hot Rolled Carbon Steel Sheet and Strip" */
    public static function productName(string $title): string
    {
        $spec = 'spe(?:c|ci|i)fi?cati?ons?';
        $t = str_replace(['–', '—', '�', '”', '“'], '-', $title);
        $t = preg_replace(['/\(cid:\d+\)/', '/(?:[ÃÂ][^\sA-Za-z0-9]*)+/u'], ' ', $t);
        $t = preg_replace('/\(?\s*\b(?:first|second|third|fourth|fifth|sixth|seventh)\s+revision\b(?:\s+of\s+IS\s*[\d\s:]+)?\s*\)?/i', '', $t);
        $t = preg_replace('/\(\s*superseding[^)]*\)/i', '', $t);
        if (substr_count($t, '(') > substr_count($t, ')')) {
            $t = substr($t, 0, strrpos($t, '('));
        }
        // The IS number already says which part/section; keep only the words that name the product.
        $t = preg_replace('/\bparticular requirements\b(?:\s*(?:for|of)\s*(?:the\s+)?(?:basic\s+)?safety(?:\s+and\s+essential\s+performance)?)?(?:\s*(?:of|for)\b)?/i', ' | ', $t);
        $t = preg_replace('/[\s:\-]*\b(?:part|sec(?:tion)?)\s*\d+[A-Z]?\b[\s:\-]*/i', ' | ', $t);
        $t = preg_replace("/^\\s*{$spec}\\s+(?:for|of)\\s+/i", '', $t);
        $t = preg_replace("/[\\s:\\-]*\\b{$spec}\\b(?:\\s+[a-z]{1,6})?\\s*(?=\\||$)/i", ' | ', $t);
        $t = preg_replace('/\s*[:\-|]\s*requirements\s*$/i', '', $t);
        $t = preg_replace('/[\s,]*\|[\s|:,\-]*/', ' | ', trim(preg_replace('/\s+/', ' ', $t)));
        $t = trim($t, ' -:;,.|');
        $t = str_replace(' | ', ' – ', $t);

        $letters = preg_replace('/[^A-Za-z]/', '', $t);
        if ($letters !== '' && (strtoupper($letters) === $letters || strtolower($letters) === $letters)) {
            return self::titleCase($t);
        }

        $small = implode('|', self::SMALL_WORDS);

        return Str::ucfirst(preg_replace_callback("/(?<=\\s)({$small})(?=\\s)/i", fn ($m) => strtolower($m[1]), $t));
    }

    public static function titleCase(string $text): string
    {
        $words = preg_split('/(\s+|(?<=[\/(-])|(?=[\/)-]))/', strtolower($text), -1, PREG_SPLIT_DELIM_CAPTURE);
        $first = true;

        return collect($words)->map(function (string $w) use (&$first) {
            if (trim($w) === '' || preg_match('/^[\/()\-]$/', $w)) {
                return $w;
            }
            $upper = strtoupper($w);
            $out = match (true) {
                in_array(rtrim($upper, '.,;:'), self::ACRONYMS, true) => $upper,
                ! $first && in_array($w, self::SMALL_WORDS, true) => $w,
                default => Str::ucfirst($w),
            };
            $first = false;

            return $out;
        })->implode('');
    }

    public static function rupees(int|float $value, int $decimals = 0): string
    {
        $neg = $value < 0;
        $whole = (string) (int) floor(abs($value));
        $fraction = $decimals ? substr(number_format(abs($value) - floor(abs($value)), $decimals), 1) : '';
        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }
        if ($decimals && rtrim($fraction, '0') === '.') {
            $fraction = '';
        }

        return ($neg ? '-' : '').$whole.$fraction;
    }

    /** "1,50,000 - 2,50,000 / model" → [150000, 250000, 'per model'] */
    public static function priceRange(?string $text): array
    {
        preg_match_all('/\d[\d,]*/', (string) $text, $m);
        $nums = array_map(fn ($n) => (int) str_replace(',', '', $n), $m[0]);
        $per = match (true) {
            (bool) preg_match('/\(([^)]+)\)/', (string) $text, $u) => $u[1],
            (bool) preg_match('/\/\s*([a-z][a-z ]*)/i', (string) $text, $u) => 'per '.trim($u[1]),
            default => null,
        };

        return [$nums[0] ?? null, $nums[1] ?? ($nums[0] ?? null), $per];
    }
}
