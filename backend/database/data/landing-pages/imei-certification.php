<?php

$device = [
    'Model name and number, exactly as it will ship',
    'Radio technology and bands supported',
    'SIM type – physical, eSIM or both',
    'Form factor and product photographs',
    'Expected production volume and launch date',
    'Factory name and address',
];

return [
    'title' => 'Google Ads – IMEI certification',
    'path' => 'lp/imei-certification',
    'service_path' => 'certification/india/imei-icdr-registration',
    'stat' => ['value' => 'Mandatory', 'label' => 'ICDR, to import or sell'],
    'seo' => [
        'title' => 'IMEI Certification – ICDR Registration & TAC | Instacertify',
        'description' => 'IMEI certification for device importers, manufacturers and brand owners: ICDR registration and GSMA brand and TAC allocation, filed right first time.',
        'keyword' => 'IMEI certification',
    ],
    'content' => [
        'hero' => [
            'headline' => 'IMEI Certification – ICDR Registration & GSMA TAC Allocation',
            'subheadline' => 'For device importers, manufacturers and brand owners. We work out whether you need ICDR registration, your own TAC, or both – then handle the filing so it’s right first time.',
            'points' => [
                'ICDR registration for importers and manufacturers',
                'GSMA brand registration and TAC allocation',
                'Phones, trackers, routers, IoT and eSIM wearables',
                'Named case manager, 3 offices in India',
            ],
            'price_note' => 'Itemised quote after a short call – no charge for the assessment',
            'cta' => 'Get my IMEI quote',
            'form_title' => 'Get your IMEI / ICDR quote',
            'form_subtitle' => 'Tell us the device and how many models. We’ll reply with what applies, the cost and the timeline.',
        ],
        'options' => [
            'heading' => 'ICDR registration or TAC allocation – which one are you?',
            'intro' => '“IMEI certification” means two different things, depending on whether your devices already carry IMEIs or you need new ones.',
            'price_label' => 'Price',
            'items' => [
                [
                    'title' => 'ICDR registration',
                    'audience' => 'Importers and manufacturers of devices that already carry valid IMEIs',
                    'timeline' => 'Before the goods move',
                    'price' => 'Itemised quote',
                    'points' => ['IMEIs registered on the DoT’s ICDR portal', 'Required for every device with a SIM or eSIM', 'No exemption for third-party brands'],
                ],
                [
                    'title' => 'TAC allocation',
                    'audience' => 'Brand owners who want their own IMEI numbers',
                    'timeline' => 'Set by brand approval',
                    'price' => 'Itemised quote',
                    'points' => ['GSMA primary and brand registration', 'Brand-name approval handled', 'TAC per model, IMEIs generated'],
                ],
                [
                    'title' => 'Both',
                    'audience' => 'Launching your own brand and selling it in India',
                    'timeline' => 'Planned together',
                    'price' => 'Itemised quote',
                    'points' => ['TAC first, then ICDR', 'One document set for both', 'One case manager'],
                ],
            ],
        ],
        'compare' => [
            'heading' => 'Most IMEI applications stall at the same step',
            'intro' => 'Brand-name approval is where TAC applications get stuck – a name too close to an existing brand, or weak trademark evidence. On ICDR, most of the pain is a rejected or incomplete filing, not the rule itself.',
            'bad' => [
                'TAC allocation sold to importers who only need ICDR',
                'Brand filed with no trademark evidence',
                'Device profile that doesn’t match what actually ships',
                'IoT devices found to be in scope at customs',
            ],
            'good' => [
                'We map which of the two you need before quoting',
                'Trademark position checked before the brand application',
                'TAC profile matched to the shipping model',
                'BIS, WPC and LMPC planned alongside if your device needs them',
            ],
        ],
        'steps' => [
            'heading' => 'How it works',
            'items' => [
                ['title' => 'Tell us the device', 'text' => 'Model, SIM type, volumes and whether you import or manufacture.'],
                ['title' => 'We map the route', 'text' => 'ICDR, TAC or both – and anything else your device needs.'],
                ['title' => 'Documents & filing', 'text' => 'Company, trademark and device documents filed correctly.'],
                ['title' => 'Approval & IMEIs', 'text' => 'TAC allocated, IMEIs generated and registered on ICDR.'],
            ],
        ],
        'benefits' => [
            'heading' => 'Why device companies choose Instacertify',
            'items' => [
                ['icon' => 'mobile', 'title' => 'Every SIM device', 'text' => 'Phones, tablets, trackers, routers, IoT modules and eSIM wearables.'],
                ['icon' => 'verified', 'title' => 'Brand approval handled', 'text' => 'The step most applications stall on.'],
                ['icon' => 'inventory_2', 'title' => 'One partner for the bundle', 'text' => 'BIS CRS, WPC ETA, IMEI/ICDR and LMPC under one case manager.'],
                ['icon' => 'support_agent', 'title' => 'Stuck files taken over', 'text' => 'Rejected elsewhere? We’ll tell you whether it’s fixable.'],
            ],
        ],
        'checklist' => [
            'heading' => 'What we’ll ask you for',
            'intro' => 'Send what you have. We’ll tell you what’s missing on the first call.',
            'groups' => [
                [
                    'title' => 'Company & brand',
                    'items' => [
                        'Certificate of incorporation and company PAN',
                        'GST registration',
                        'Trademark certificate or application for the brand name',
                        'Authorised signatory details and ID',
                        'IEC, if you’re importing',
                        'Manufacturing agreement, if a third party builds it',
                    ],
                ],
                ['title' => 'Device', 'items' => $device],
            ],
            'note' => 'No trademark yet? Say so on the call – it changes the sequence.',
        ],
        'related' => [
            'heading' => 'Devices that need IMEI registration',
            'intro' => 'Also searched as IMEI certificate, IMEI registration, ICDR registration and TAC allocation.',
            'keywords' => [
                'Mobile phones', 'Feature phones', 'Tablets with SIM', 'GPS & vehicle trackers', 'AIS 140 devices', '4G/5G routers & dongles',
                'POS terminals', 'Smart meters', 'IoT & M2M modules', 'eSIM smartwatches', 'Kids’ trackers', 'Telematics units',
            ],
        ],
        'faqs' => [
            'heading' => 'Questions we get on the first call',
            'items' => [
                ['question' => 'What is ICDR registration?', 'answer' => 'ICDR (Indian Counterfeited Device Restriction) is the Department of Telecommunications’ system for recording the IMEIs of devices made in or imported into India before they’re sold. Devices that aren’t on it can be blocked from Indian networks.'],
                ['question' => 'What is a TAC, and how is it different from an IMEI?', 'answer' => 'The Type Allocation Code is the first 8 digits of every IMEI and identifies the device model. The GSMA issues one TAC per model to the brand owner. The full 15-digit IMEI adds a 6-digit serial number and a check digit.'],
                ['question' => 'We import devices made by a third party. Do we need our own TAC?', 'answer' => 'If the devices already carry valid IMEIs from the manufacturer’s TAC, you don’t need your own TAC – but the IMEIs must be registered under ICDR before the consignment lands. If you sell under your own brand, the brand owner generally needs its own TAC.'],
                ['question' => 'Does this apply to IoT and M2M devices?', 'answer' => 'Yes. The requirement follows cellular connectivity. A smart meter, GPS tracker, POS terminal or industrial sensor with a SIM or eSIM needs IMEIs just like a handset.'],
                ['question' => 'How long does TAC allocation take?', 'answer' => 'The mechanical steps are quick. Brand-name approval sets the timeline, and it depends on your trademark position. We give you a realistic view on the first call once we’ve seen your brand and trademark status.'],
                ['question' => 'Do I need a TAC for every model?', 'answer' => 'Generally yes – one TAC covers one model. Variants that differ in radio configuration or hardware usually need their own; cosmetic variants sometimes don’t. Send us your model list and we’ll tell you how many you need.'],
                ['question' => 'Are you connected to the DoT or GSMA?', 'answer' => 'No. We are a private consultancy with no affiliation to the GSMA or the Department of Telecommunications. TACs are issued by the GSMA and ICDR registration is administered by the DoT; we prepare and manage your application.'],
            ],
        ],
        'closing' => [
            'heading' => 'Tell us the device. We’ll tell you what applies.',
            'text' => 'Send the model and how it reaches the market. You’ll get a straight answer on ICDR, TAC and anything else it needs.',
        ],
    ],
];
