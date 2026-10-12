<?php

namespace App\Support;

/**
 * Suggests certification tags (scheme slugs) and a work type for audits and events.
 *
 * Title matches win: the summary is only read when the title names no scheme, so a
 * "BIS CRS strategy" case study isn't also tagged ISI because its text cites an IS number.
 */
class SchemeClassifier
{
    /** Default tags, in menu order. Editors can add more under Resources → Certification tags. */
    public const SCHEMES = [
        'bis-isi' => 'BIS ISI mark',
        'bis-crs' => 'BIS CRS registration',
        'imei' => 'IMEI / ICDR registration',
        'wpc' => 'WPC ETA approval',
        'tec' => 'TEC / MTCTE',
        'bee' => 'BEE star label',
        'epr' => 'EPR',
        'lmpc' => 'Legal Metrology (LMPC)',
        'msds' => 'MSDS / SDS',
        'iso' => 'ISO certification',
        'cdsco' => 'CDSCO',
        'g-mark' => 'G-Mark (GCC)',
        'saber' => 'SABER (Saudi Arabia)',
        'soncap' => 'SONCAP (Nigeria)',
        'ce' => 'CE marking',
        'testing' => 'Product testing',
        'psi' => 'Pre-shipment inspection',
    ];

    public const PATTERNS = [
        'bis-crs' => '~(?-i:\bCRS\b)|compulsory registration~i',
        'bis-isi' => '~(?-i:\bISI\b|\bFMCS\b|\bIS\s?\d{3,5}\b)|\bBIS (?:audit|licen[cs]e)~i',
        'imei' => '~(?-i:\bIMEI\b|\bICDR\b)~',
        'wpc' => '~(?-i:\bWPC\b|\bETA\b)~',
        'tec' => '~(?-i:\bTEC\b|\bMTCTE\b)~',
        'bee' => '~(?-i:\bBEE\b)|star (?:label|rating)~i',
        'epr' => '~(?-i:\bEPR\b)|extended producer|plastic waste|e-waste|battery waste~i',
        'lmpc' => '~(?-i:\bLMPC\b)|legal metrology~i',
        'msds' => '~(?-i:\bM?SDS\b)|safety data sheet~i',
        'iso' => '~(?-i:\bISO\s?\d{4,5}\b)~',
        'cdsco' => '~(?-i:\bCDSCO\b)|medical device~i',
        'g-mark' => '~\bG-?Mark\b|(?-i:\bGSO\b)~i',
        'saber' => '~(?-i:\bSABER\b)~',
        'soncap' => '~(?-i:\bSONCAP\b)~',
        'ce' => '~(?-i:\bCE\b) (?:mark|certif)~i',
        'testing' => '~(?-i:\bEMC\b|\bEMI\b|\bIP\d{2}\b)|electromagnetic|ingress protection|product testing|lab testing~i',
        'psi' => '~pre-?shipment|third[- ]party inspection~i',
    ];

    /** What exhibitors at a trade fair in each industry usually need in India. Used for events only. */
    public const INDUSTRY_SCHEMES = [
        'Electrical & Electronics' => ['bis-crs', 'testing'],
        'Electronics & IT Goods' => ['bis-crs'],
        'Electrical Appliances' => ['bis-isi', 'bee'],
        'Lighting Products' => ['bis-crs', 'bee'],
        'Batteries & Power' => ['bis-crs', 'epr'],
        'Solar & Renewable' => ['bis-crs'],
        'Cables & Wires' => ['bis-isi'],
        'Steel Products' => ['bis-isi'],
        'Non-Ferrous Metals' => ['bis-isi'],
        'Cement & Construction Materials' => ['bis-isi'],
        'Rubber Products' => ['bis-isi'],
        'Toys' => ['bis-isi'],
        'Footwear' => ['bis-isi'],
        'Helmets' => ['bis-isi'],
        'Pressure Cookers' => ['bis-isi'],
        'Paper & Packaging' => ['epr'],
        'PVC & Plastic Products' => ['epr'],
        'Chemicals' => ['msds', 'bis-isi'],
        'Paints, Coatings & Adhesives' => ['msds'],
        'Petroleum & Lubricants' => ['msds'],
        'Medical Devices & Textiles' => ['cdsco'],
        'Food, Dairy & Beverages' => ['testing'],
    ];

    public const WORK_TYPES = [
        'audit' => 'Factory audit',
        'inspection' => 'Inspection',
        'case_study' => 'Case study',
    ];

    /** @return list<string> scheme slugs */
    public static function classify(?string $title, ?string $summary = null, ?string $industry = null): array
    {
        $fromTitle = self::match((string) $title);
        $slugs = $fromTitle ?: self::match(strip_tags((string) $summary));

        return array_values(array_unique([...$slugs, ...(self::INDUSTRY_SCHEMES[$industry] ?? [])]));
    }

    public static function workType(?string $title, ?string $summary = null): ?string
    {
        $title = (string) $title;
        $text = $title.' '.strip_tags((string) $summary);

        return match (true) {
            (bool) preg_match('~\bhow instacertify help|\bcase study\b|\bstrategy\b~i', $title) => 'case_study',
            (bool) preg_match('~\binspection\b|pre-?shipment~i', $title) => 'inspection',
            (bool) preg_match('~\baudit\b~i', $text) => 'audit',
            default => null,
        };
    }

    /** @return list<string> */
    private static function match(string $text): array
    {
        return array_keys(array_filter(self::PATTERNS, fn (string $pattern) => preg_match($pattern, $text) === 1));
    }
}
