<?php

return [
    'title' => 'Google Ads – IMEI for device manufacturers',
    'path' => 'lp/imei-registration-for-manufacturers',
    'service_path' => 'certification/india/imei-icdr-registration',
    'stat' => ['value' => '8 steps', 'label' => 'Mapped from GSMA to ICDR'],
    'seo' => [
        'title' => 'IMEI Registration for Manufacturers | Instacertify',
        'description' => 'For device makers and brand owners: GSMA brand registration, TAC allocation, IMEI generation and ICDR registration, handled through brand approval.',
        'keyword' => 'IMEI registration for manufacturer',
    ],
    'content' => [
        'hero' => [
            'headline' => 'IMEI Registration for Device Manufacturers – TAC & ICDR',
            'subheadline' => 'Building phones, trackers or IoT devices in India, or under your own brand abroad? We handle GSMA brand registration, TAC allocation, IMEI generation and ICDR registration.',
            'points' => [
                'GSMA primary and brand registration',
                'Brand-name approval – the step that stalls most files',
                'TAC per model and IMEIs for your production run',
                'ICDR registration before sale in India',
            ],
            'price_note' => 'Itemised quote after a short call – no charge for the assessment',
            'cta' => 'Get my TAC & ICDR quote',
            'form_title' => 'Get your manufacturer IMEI quote',
            'form_subtitle' => 'Tell us the device, your brand and how many models. We’ll reply with the route and timeline.',
        ],
        'options' => [
            'heading' => 'Own factory, contract-made or building for others?',
            'intro' => 'Who registers what depends on whose brand goes on the device.',
            'price_label' => 'Price',
            'items' => [
                [
                    'title' => 'Own brand, own factory',
                    'audience' => 'Indian manufacturers selling devices under their own name',
                    'timeline' => 'Set by brand approval',
                    'price' => 'Itemised quote',
                    'points' => ['GSMA brand registration', 'TAC per model', 'ICDR before sale'],
                ],
                [
                    'title' => 'Own brand, contract-made',
                    'audience' => 'Brand owners whose devices are built by an EMS or ODM partner',
                    'timeline' => 'Set by brand approval',
                    'price' => 'Itemised quote',
                    'points' => ['Brand owner holds the TAC', 'Manufacturing agreement on file', 'ICDR for the IMEIs'],
                ],
                [
                    'title' => 'EMS / ODM factory',
                    'audience' => 'Factories in India building devices for other brands',
                    'timeline' => 'Planned with production',
                    'price' => 'Itemised quote',
                    'points' => ['IMEIs from the brand owner’s TAC', 'ICDR registration for domestic production', 'Documents ready for each brand'],
                ],
            ],
        ],
        'compare' => [
            'heading' => 'Eight steps, and most applications stall at step four',
            'intro' => 'Primary registration, brand application, documents, brand approval, TAC profile, TAC allocation, IMEI generation and ICDR. Brand approval is where weak trademark evidence or a name too close to an existing brand sends you back.',
            'bad' => [
                'Brand filed with no trademark evidence',
                'TAC profile that doesn’t match the production model',
                'One TAC assumed to cover every variant',
                'ICDR left until the first batch is ready',
            ],
            'good' => [
                'Trademark position checked before the brand application',
                'Device profile matched to what you’ll actually ship',
                'TAC count worked out per model and radio variant',
                'ICDR planned alongside production',
            ],
        ],
        'steps' => [
            'heading' => 'From GSMA to ICDR',
            'items' => [
                ['title' => 'GSMA registration', 'text' => 'Your company registered as an applicant.'],
                ['title' => 'Brand approval', 'text' => 'Brand application and trademark evidence, through to approval.'],
                ['title' => 'TAC allocated', 'text' => 'Device profile prepared and the 8-digit TAC issued per model.'],
                ['title' => 'IMEIs generated', 'text' => 'IMEIs produced for your run with correct check digits.'],
                ['title' => 'ICDR registration', 'text' => 'IMEIs registered with the DoT before the devices are sold.'],
            ],
        ],
        'benefits' => [
            'heading' => 'Why manufacturers choose Instacertify',
            'items' => [
                ['icon' => 'factory', 'title' => 'Built for manufacturers', 'text' => 'Own brand, contract-made or EMS – we know who registers what.'],
                ['icon' => 'verified', 'title' => 'Brand approval handled', 'text' => 'The step that decides your timeline.'],
                ['icon' => 'tune', 'title' => 'Right number of TACs', 'text' => 'Often fewer than you expect, once variants are mapped properly.'],
                ['icon' => 'inventory_2', 'title' => 'The full approval set', 'text' => 'BIS CRS, WPC ETA and LMPC planned with your IMEI work.'],
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
                        'Manufacturing agreement, if a third party builds it',
                    ],
                ],
                [
                    'title' => 'Device',
                    'items' => [
                        'Model name and number, exactly as it will ship',
                        'Radio technology and bands supported',
                        'SIM type – physical, eSIM or both',
                        'Form factor and product photographs',
                        'Expected production volume and launch date',
                        'Factory name and address',
                    ],
                ],
            ],
            'note' => 'No trademark yet? Say so on the call – it changes the sequence.',
        ],
        'related' => [
            'heading' => 'Devices we register for manufacturers',
            'intro' => 'Also searched as IMEI registration for manufacturer, TAC allocation, GSMA TAC, IMEI number generation and ICDR registration.',
            'keywords' => [
                'Smartphones', 'Feature phones', 'Tablets', 'GPS trackers', '4G/5G routers & CPE', 'POS terminals',
                'Smart meters', 'IoT & M2M modules', 'eSIM wearables', 'Telematics units',
            ],
        ],
        'faqs' => [
            'heading' => 'Questions manufacturers ask',
            'items' => [
                ['question' => 'What is a TAC, and how is it different from an IMEI?', 'answer' => 'The Type Allocation Code is the first 8 digits of every IMEI and identifies the device model. The GSMA issues one TAC per model to the brand owner. The full 15-digit IMEI adds a 6-digit serial number and a check digit.'],
                ['question' => 'Do we need a TAC for every model and variant?', 'answer' => 'Generally one TAC covers one model. Variants that differ in radio configuration or hardware usually need their own; cosmetic variants sometimes don’t. Send us your model list and we’ll tell you how many you need.'],
                ['question' => 'How long does TAC allocation take?', 'answer' => 'The mechanical steps are quick. Brand-name approval sets the timeline, and it depends on your trademark position. A clean application with a registered trademark moves quickly; a contested name can take weeks.'],
                ['question' => 'We don’t have a registered trademark yet. Does that block us?', 'answer' => 'Not necessarily. A pending trademark application is usually workable. No filing at all is the most common reason brand approval is refused, so raise it on the first call.'],
                ['question' => 'Do manufacturers need ICDR, or only importers?', 'answer' => 'Both. If you build devices with IMEIs and sell them in India, the IMEIs must be registered under ICDR. Domestic production isn’t exempt.'],
                ['question' => 'Our application was rejected by a previous consultant. Can you take it over?', 'answer' => 'Yes. Share the correspondence and application reference. We’ll tell you whether the file can be corrected or a fresh application is faster, before you commit to either.'],
                ['question' => 'Are you connected to the GSMA or DoT?', 'answer' => 'No. We are a private consultancy with no affiliation to the GSMA or the Department of Telecommunications. TACs are issued by the GSMA and ICDR is administered by the DoT; we prepare and manage your application.'],
            ],
        ],
        'closing' => [
            'heading' => 'Tell us the device and the brand',
            'text' => 'We’ll map the route from GSMA registration to ICDR, and give you a realistic timeline.',
        ],
    ],
];
