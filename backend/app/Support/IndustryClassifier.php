<?php

namespace App\Support;

/**
 * Suggests an industry subcategory from an article or event title.
 *
 * Keywords: a trailing * matches word stems ("cable*" → cables, cabling); keywords containing
 * capitals are case-sensitive so acronyms like LED, BEE or IT don't match "led", "bee" or "it".
 * Earlier industries win ties, so specific ones come first.
 */
class IndustryClassifier
{
    public const INDUSTRIES = [
        'Pressure Cookers' => ['pressure cooker*'],
        'Helmets' => ['helmet*'],
        'Footwear' => ['footwear', 'shoe', 'shoes', 'sandal*'],
        'Leather Products' => ['leather'],
        'Toys' => ['toy', 'toys', 'childcare'],
        'LPG & Gas Equipment' => ['LPG', 'CNG', 'PESO', 'gas cylinder*', 'gas stove*', 'gas regulator*', 'gas leak*'],
        'Fire Safety Products' => ['fire extinguisher*', 'fire safety', 'fire detect*', 'fire alarm*', 'sprinkler*'],
        'Safety & PPE' => ['PPE', 'personal protective', 'safety glove*', 'safety shoe*', 'respirator*'],
        'Batteries & Power' => ['batter*', 'power bank*', 'lithium', 'UPS'],
        'Solar & Renewable' => ['solar', 'photovoltaic', 'renewable*', 'hydrogen', 'wind turbine*'],
        'Energy efficiency (BEE)' => ['BEE', 'star label*', 'star rating*', 'energy efficien*', 'energy label*'],
        'Lighting Products' => ['LED', 'LEDs', 'lamp', 'lamps', 'luminaire*', 'lighting', 'emergency light*', 'street light*', 'flashlight*'],
        'Cables & Wires' => ['cable*', 'wire', 'wires', 'wiring'],
        'Electrical Appliances' => ['appliance*', 'washing machine*', 'refrigerat*', 'air condition*', 'AC', 'ACs', 'HVAC', 'ACREX', 'induction', 'stove*', 'mixer*', 'grinder*', 'kettle*', 'heater*', 'microwave*', 'fan', 'fans', 'water purifier*'],
        'Electronics & IT Goods' => ['laptop*', 'smartphone*', 'mobile phone*', 'monitor', 'monitors', 'printer*', 'television*', 'smart watch*', 'smartwatch*', 'IT equipment', 'IT goods', 'IT and AV', '62368', 'IoT', 'IMEI', 'MAC address*', 'FCC', 'software', 'wireless', 'Bluetooth', 'WPC', 'MTCTE', 'telecom*', 'router*', 'CCTV', 'semiconductor*', 'webcam*', 'hard disk*', 'HDD*', 'screen protector*', 'display technolog*', 'embedded system*'],
        'Electrical & Electronics' => ['electronic*', 'electrical*', 'adapter*', 'adaptor*', 'charger*', 'switches', 'switchgear', 'socket*', 'plugs', 'inverter*', 'e-waste', 'EMC', 'EMI', 'CISPR', 'RoHS', 'electromagnetic', 'smart meter*', 'power device*'],
        'Steel Products' => ['steel*', 'TMT', 'ferro*', 'iron', 'sheet metal', 'galvani*', 'earthing'],
        'Non-Ferrous Metals' => ['aluminium', 'aluminum', 'copper', 'zinc', 'brass', 'nickel', 'lead sheet*', 'solder*', 'metal', 'metals', 'alloy*'],
        'Cement & Construction Materials' => ['cement*', 'concrete', 'tile adhesive*', 'tile', 'tiles', 'construction', 'brick*', 'gypsum', 'building material*'],
        'Paints, Coatings & Adhesives' => ['paint*', 'coating*', 'adhesive*', 'varnish*', 'sealant*'],
        'Glass & Ceramics' => ['glass', 'glassware', 'ceramic*', 'sanitary ware', 'sanitaryware'],
        'Wood & Plywood Products' => ['plywood', 'wood', 'wooden', 'timber', 'particle board*', 'MDF'],
        'Paper & Packaging' => ['paper', 'papers', 'packaging', 'pulp', 'carton*', 'corrugat*', 'printing', 'print expo'],
        'PVC & Plastic Products' => ['plastic*', 'PVC', 'polymer*', 'HDPE', 'polyethylene', 'polypropylene', 'polystyrene', 'woven sack*', 'water tank*'],
        'Rubber Products' => ['rubber', 'tyre*', 'tire', 'tires'],
        'Chemicals' => ['chemical*', 'acid', 'acids', 'solvent*', 'SDS', 'safety data sheet*', 'REACH', 'caustic', 'soda ash', 'methanol', 'acrylate*', 'benzene*'],
        'Pesticides & Agro-Chemicals' => ['pesticide*', 'fertili*', 'agrochemical*', 'insecticide*', 'herbicide*', 'agricultur*', 'farming'],
        'Petroleum & Lubricants' => ['petroleum', 'petrochemical*', 'lubricant*', 'engine oil*', 'diesel'],
        'Food, Dairy & Beverages' => ['food', 'foods', 'FSSAI', 'dairy', 'milk', 'beverage*', 'drinks', 'drinking water', 'mineral water', 'edible oil*', 'spice*', 'fresh produce', 'fruit*'],
        'Soaps, Detergents & Cosmetics' => ['cosmetic*', 'soap*', 'detergent*', 'personal care', 'beauty', 'skincare', 'shampoo*'],
        'Textiles' => ['textile*', 'fabric*', 'yarn*', 'garment*', 'apparel', 'cotton', 'coir', 'crop cover*'],
        'Medical Devices & Textiles' => ['medical', 'CDSCO', 'surgical', 'diagnostic*', 'hospital*', 'healthcare'],
        'Pumps, Valves & Irrigation' => ['pump', 'pumps', 'valve*', 'irrigation'],
        'Machinery, Tools & Instruments' => ['machinery', 'machine tool*', 'tools', 'drill*', 'sewing machine*', 'instrument*', 'robot*', 'automation', 'lab equipment', 'laboratory equipment', 'analytica', 'lab expo', 'assembly', 'mould*', 'mold', 'molds', 'die & mold', 'additive manufacturing', '3D printing', 'laser*', 'photonics'],
        'Hardware & Fittings' => ['hardware', 'fittings', 'fastener*', 'bolts', 'screws', 'locks'],
        'Furniture & Storage' => ['furniture', 'chairs', 'cabinet*'],
        'Automotive & Cycle Components' => ['automotive', 'automechanika', 'auto maintenance', 'car-mecha', 'vehicle*', 'bicycle*', 'cycles', 'EV', 'AIS', 'auto parts', 'motorcycle*'],
        'Consumer Goods' => ['agarbatti', 'incense', 'kitchenware', 'houseware*', 'utensil*', 'consumer goods', 'stationery'],
    ];

    /** Industry pages each subcategory belongs to (by industry slug). */
    public const INDUSTRY_PAGES = [
        'Electrical & Electronics' => 'electronic-industry',
        'Electrical Appliances' => 'electronic-industry',
        'Electronics & IT Goods' => 'electronic-industry',
        'Cables & Wires' => 'electronic-industry',
        'Lighting Products' => 'electronic-industry',
        'Batteries & Power' => 'electronic-industry',
        'Toys' => 'toys-industry',
        'PVC & Plastic Products' => 'polymers-plastics-industry',
        'Rubber Products' => 'polymers-plastics-industry',
        'Soaps, Detergents & Cosmetics' => 'cosmetics-industry',
        'Food, Dairy & Beverages' => 'food-industry',
    ];

    /** @var array<string, string>|null */
    private static ?array $patterns = null;

    public static function classify(?string $title, ?string $summary = null): ?string
    {
        $title = (string) $title;
        $summary = strip_tags((string) $summary);
        $best = null;
        $bestScore = 0;

        foreach (self::patterns() as $industry => $pattern) {
            $inTitle = preg_match_all($pattern, $title);
            $inSummary = preg_match_all($pattern, $summary);
            if (! $inTitle && $inSummary < 2) {
                continue;
            }

            $score = 3 * $inTitle + $inSummary;
            if ($score > $bestScore) {
                [$best, $bestScore] = [$industry, $score];
            }
        }

        return $best;
    }

    /** @return array<string, string> */
    private static function patterns(): array
    {
        return self::$patterns ??= collect(self::INDUSTRIES)->map(function (array $keywords) {
            [$sensitive, $insensitive] = collect($keywords)->partition(fn (string $k) => $k !== strtolower($k));
            $regex = fn ($list) => $list->map(fn (string $k) => preg_quote(rtrim($k, '*'), '~').(str_ends_with($k, '*') ? '\w*' : '\b'))->implode('|');

            return '~'.collect([
                $sensitive->isNotEmpty() ? '(?-i:\b(?:'.$regex($sensitive).'))' : null,
                $insensitive->isNotEmpty() ? '\b(?:'.$regex($insensitive).')' : null,
            ])->filter()->implode('|').'~iu';
        })->all();
    }
}
