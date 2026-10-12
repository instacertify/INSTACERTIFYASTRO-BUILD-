<?php

namespace App\Support;

use App\Models\Service;
use App\Models\Setting;

/**
 * Industries the Product testing page is organised by. A testing service can sit in several.
 */
class TestCategories
{
    /** @var array<string, array{label: string, icon: string, blurb: string}> */
    public const ALL = [
        'electrical-electronics' => ['label' => 'Electronics & Electrical', 'icon' => 'electrical_services', 'blurb' => 'Safety, EMI/EMC, climatic, IP and battery testing for electrical and electronic products, lighting and power equipment.'],
        'it-av-equipment' => ['label' => 'IT & AV Equipment', 'icon' => 'devices', 'blurb' => 'Safety, EMC, RF and energy testing for computers, mobile phones, TVs, audio-video and telecom equipment.'],
        'chemicals' => ['label' => 'Chemicals', 'icon' => 'science', 'blurb' => 'Composition, purity, hazard and restricted-substance testing, including REACH.'],
        'cosmetics-personal-care' => ['label' => 'Cosmetics & Personal Care', 'icon' => 'spa', 'blurb' => 'Microbial, stability, heavy-metal and skin-safety testing for cosmetics and personal care products.'],
        'furniture' => ['label' => 'Furniture', 'icon' => 'chair', 'blurb' => 'Strength, stability, durability and material testing for home, office and outdoor furniture.'],
        'home-kitchen' => ['label' => 'Home & Kitchen', 'icon' => 'blender', 'blurb' => 'Safety, EMC and standby-power testing for household and kitchen appliances.'],
        'metals' => ['label' => 'Metals & Alloys', 'icon' => 'hardware', 'blurb' => 'Chemical composition, mechanical and corrosion testing for metals and alloys.'],
        'plastics-polymers' => ['label' => 'Plastics & Polymers', 'icon' => 'water_bottle', 'blurb' => 'Mechanical, thermal and chemical testing for plastics, polymers and composites.'],
        'paints-coatings' => ['label' => 'Paints & Coatings', 'icon' => 'format_paint', 'blurb' => 'Salt-spray and corrosion testing for paints, coatings and finishes.'],
        'construction' => ['label' => 'Construction & Building Materials', 'icon' => 'foundation', 'blurb' => 'Cement, steel, aggregate and building-product testing to Indian Standards.'],
        'textiles-leather' => ['label' => 'Textiles & Leather', 'icon' => 'checkroom', 'blurb' => 'Restricted-substance and quality testing for fabrics, garments and leather goods.'],
        'food-beverages' => ['label' => 'Food & Beverages', 'icon' => 'restaurant', 'blurb' => 'Nutritional, microbial, pesticide-residue and shelf-life testing to FSSAI norms.'],
        'agriculture-fertilizers' => ['label' => 'Agriculture & Fertilizers', 'icon' => 'agriculture', 'blurb' => 'Pesticide-residue testing for farm produce, and composition testing for fertilizers and agrochemicals.'],
        'toys-childcare' => ['label' => 'Toys & Childcare Products', 'icon' => 'toys', 'blurb' => 'Mechanical, chemical and flammability testing for toys and childcare products to IS, EN and ASTM standards.'],
        'automotive' => ['label' => 'Automotive & Transportation', 'icon' => 'directions_car', 'blurb' => 'Vibration, shock, climatic and EV battery testing for vehicles, railway and on-board equipment.'],
        'medical-healthcare' => ['label' => 'Medical Devices & Healthcare Products', 'icon' => 'medical_services', 'blurb' => 'Safety, performance and biocompatibility testing for medical devices and healthcare products.'],
        'packaging' => ['label' => 'Packaging & Paper Products', 'icon' => 'package_2', 'blurb' => 'Strength, barrier and food-contact testing for packaging, paper and board.'],
        'environment-water' => ['label' => 'Environmental & Water Testing', 'icon' => 'water_drop', 'blurb' => 'Drinking water, effluent, soil and air testing.'],
        'rubber-tyres' => ['label' => 'Rubber, Tyres & Elastomers', 'icon' => 'tire_repair', 'blurb' => 'Mechanical, ageing and chemical testing for rubber, tyres and elastomer products.'],
        'consumer-products' => ['label' => 'General Consumer Products', 'icon' => 'shopping_bag', 'blurb' => 'Safety, chemical and restricted-substance testing for everyday consumer goods.'],
    ];

    /** Industries that were merged or renamed, and where their pages now point. */
    public const MOVED = [
        'it-telecom' => 'it-av-equipment',
        'food-agri' => 'food-beverages',
        'pharma-healthcare' => 'medical-healthcare',
        'plastics-rubber' => 'plastics-polymers',
        'toys-consumer' => 'toys-childcare',
        'battery-energy' => 'electrical-electronics',
        'machinery-industrial' => 'electrical-electronics',
        'aerospace-defence' => 'electrical-electronics',
        'oil-gas' => 'chemicals',
        'glass-ceramics' => 'construction',
        'inspection-calibration' => null,
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $c) => $c['label'], self::ALL);
    }

    /**
     * Icons picked or uploaded under Site settings → Testing industries replace the defaults.
     *
     * @return list<array{slug: string, label: string, icon: string, iconImage: ?string, blurb: string}>
     */
    public static function forApi(): array
    {
        $custom = Setting::get('site', [])['testCategories'] ?? [];

        return collect(self::ALL)->map(fn (array $c, string $slug) => [
            'slug' => $slug,
            ...$c,
            'icon' => ($custom[$slug]['icon'] ?? null) ?: $c['icon'],
            'iconImage' => Images::url($custom[$slug]['iconImage'] ?? null),
        ])->values()->all();
    }

    /**
     * Best first guess from the service's slug and test type; editors adjust it in the admin.
     *
     * @return list<string>
     */
    public static function guess(Service $service): array
    {
        $slug = (string) $service->slug;
        $has = fn (string ...$needles) => collect($needles)->contains(fn (string $n) => str_contains($slug, $n));

        $fixed = [
            'electronics-electricals-testing' => ['electrical-electronics'],
            'wireless-testing' => ['it-av-equipment'],
            'environmental-testing' => ['electrical-electronics', 'automotive'],
            'lm79' => ['electrical-electronics'],
            'toys' => ['toys-childcare'],
            'cosmetics-testing' => ['cosmetics-personal-care'],
            'food-beverages-testing' => ['food-beverages', 'agriculture-fertilizers'],
            'chemicals-product-testing' => ['chemicals', 'agriculture-fertilizers'],
            'plastics-polymers-testing' => ['plastics-polymers', 'rubber-tyres'],
            'construction-material-testing' => ['construction'],
            'metal-alloys' => ['metals'],
            'reach-testing' => ['chemicals', 'textiles-leather', 'consumer-products'],
            'rohs-testing' => ['electrical-electronics', 'it-av-equipment'],
            'emi-emc-standards-cispr-11-cispr-32-cispr-14-1-cispr-25' => ['electrical-electronics', 'it-av-equipment', 'home-kitchen', 'automotive'],
        ];
        if (isset($fixed[$slug])) {
            return $fixed[$slug];
        }

        $categories = match ($service->group) {
            'rf-conformance', 'signaling-conformance', 'power-consumption-testing' => ['it-av-equipment'],
            default => $service->kind === 'testing' ? ['electrical-electronics'] : [],
        };

        // Computers, phones, TVs and telecom gear (IS 13252, IS/IEC 62368, IS 616, CISPR 32, ITU-T K.44).
        if ($has('13252', '62368', '16333', 'is-616', 'cispr-32', 'itu-t-k-44')) {
            $categories = ['it-av-equipment'];
        } elseif ($has('itu-t')) {
            $categories[] = 'it-av-equipment';
        }
        // Household appliances (IS 302, IS 60335, CISPR 14-1, standby power to IEC 62301).
        if ($has('is-302', 'is-60335', 'cispr-14', 'iec-62301')) {
            $categories[] = 'home-kitchen';
        }
        if ($has('mil-std167')) {
            $categories = ['automotive'];
        }
        // Road vehicles, railway and EV batteries.
        if ($has('iso-16750', 'jis-d', 'sae-j1455', 'railway', 'rolling-stock', '61373', '50125')) {
            $categories = ['automotive'];
        } elseif ($has('is-16893', 'is-16827', 'cispr-25')) {
            $categories = ['electrical-electronics', 'automotive'];
        }
        if ($has('salt')) {
            $categories = ['metals', 'paints-coatings'];
        }

        return array_values(array_unique($categories));
    }
}
