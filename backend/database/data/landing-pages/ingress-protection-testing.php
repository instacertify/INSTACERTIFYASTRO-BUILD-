<?php

return [
    'title' => 'Google Ads – Ingress protection (IP) testing',
    'path' => 'lp/ingress-protection-testing',
    'service_path' => 'ip-testing',
    'stat' => ['value' => 'IP69K', 'label' => 'Up to washdown rating'],
    'seo' => [
        'title' => 'IP Testing – IP65, IP67, IP68 & IP69K | Instacertify',
        'description' => 'Ingress protection testing to IEC 60529 at accredited partner labs. Rating set from real duty, sample reviewed before shipping, lab charge shown upfront.',
        'keyword' => 'Ingress protection testing',
    ],
    'content' => [
        'hero' => [
            'headline' => 'Ingress Protection (IP) Testing – IP65, IP67, IP68 & IP69K',
            'subheadline' => 'Dust and water ingress testing to IEC 60529 for lighting, enclosures, electronics, EV and industrial equipment – booked at a partner lab with the right rig and scope.',
            'points' => [
                'IP1X–IP6X and IPX1–IPX9, including IP69K',
                'NABL partner labs, BIS-recognised where needed',
                'Sample review before you ship – aim to pass first time',
                'Lab charge and our fee shown separately',
            ],
            'price_note' => 'Lab charge and our fee shown separately, before anything is booked',
            'cta' => 'Get my IP testing quote',
            'form_title' => 'Get your IP testing quote',
            'form_subtitle' => 'Tell us the product and where it has to survive. We’ll reply with the rating, the lab and the cost.',
        ],
        'options' => [
            'heading' => 'IP65, IP67, IP68 or IP69K – the lab setup changes',
            'intro' => 'Pick the rating the duty really needs, then book a lab that can run that exact method.',
            'price_label' => 'Price',
            'items' => [
                [
                    'title' => 'IP65 / IP66',
                    'audience' => 'Dust-tight with water-jet protection – outdoor lights, enclosures, chargers',
                    'timeline' => '1–2 weeks at the lab',
                    'price' => 'Lab + our fee',
                    'points' => ['Dust chamber run', 'Water jets at 12.5 or 100 L/min', 'Common for BIS-filed luminaires'],
                ],
                [
                    'title' => 'IP67 / IP68',
                    'audience' => 'Immersion – handsets, wearables, buried or submerged equipment',
                    'timeline' => '1–2 weeks at the lab',
                    'price' => 'Lab + our fee',
                    'points' => ['IPX7: 1 m for 30 minutes', 'IPX8: the depth and time you declare', 'Often paired with IP66'],
                ],
                [
                    'title' => 'IP69K',
                    'audience' => 'High-pressure, high-temperature washdown – food plant and vehicles',
                    'timeline' => 'Depends on rig availability',
                    'price' => 'Lab + our fee',
                    'points' => ['80 °C water at high pressure, close range', 'IEC 60529 IPX9 or ISO 20653 IP69K', 'Confirmed with your buyer before booking'],
                ],
            ],
        ],
        'compare' => [
            'heading' => 'Most failed IP tests are failures of the paperwork',
            'intro' => 'An IP rating is a test result, not a government certificate. What carries weight is the lab, its accredited scope, and whether the test conditions in the report match what you claim.',
            'bad' => [
                'Rating copied from a competitor’s datasheet',
                'Samples sent to whichever lab answered the phone',
                'Fails on a gasket or gland nobody looked at',
                'New samples, new slot, second invoice',
            ],
            'good' => [
                'Rating set from where the product really sits',
                'Lab scope checked against your rating and sample size',
                'Seals, glands, vents and drain paths reviewed before shipping',
                'Report read before you publish the rating',
            ],
        ],
        'steps' => [
            'heading' => 'How it works',
            'items' => [
                ['title' => 'We work out the rating', 'text' => 'Mounting, exposure and who is asking for it.'],
                ['title' => 'We match the lab', 'text' => 'Scope, accreditation, chamber size and rig availability.'],
                ['title' => 'Sample review', 'text' => 'Seals, glands and vents checked; we’ll tell you if we think it will fail.'],
                ['title' => 'Testing', 'text' => 'Samples logged, tests run to the standard, photos taken on the rig.'],
                ['title' => 'Report & wording', 'text' => 'The rating on your product, datasheet and box worded to match the evidence.'],
            ],
        ],
        'benefits' => [
            'heading' => 'Why manufacturers choose Instacertify',
            'items' => [
                ['icon' => 'water_drop', 'title' => 'Every rating', 'text' => 'IP1X to IP6X, IPX1 to IPX9 and IP69K.'],
                ['icon' => 'science', 'title' => 'Accredited partner labs', 'text' => 'NABL labs, BIS-recognised where your filing needs it.'],
                ['icon' => 'search', 'title' => 'Design review first', 'text' => 'Cheaper to fix a gland now than to pay for a retest slot.'],
                ['icon' => 'troubleshoot', 'title' => 'Honest retests', 'text' => 'If it fails, we show where water got in and requote honestly.'],
            ],
        ],
        'checklist' => [
            'heading' => 'Drawings, samples and an honest description of the duty',
            'intro' => 'A light under an eave and a light on a pole in open rain aren’t the same test, even if they’re the same product.',
            'groups' => [
                [
                    'title' => 'Your checklist',
                    'items' => [
                        'Product name, model number and a photograph',
                        'Overall dimensions and weight',
                        'Mounting position in service – wall, pole, floor, vehicle, handheld',
                        'Enclosure drawing with gasket, gland and vent detail, if you have it',
                        'Who asked for the rating – BIS filing, tender, buyer or your own claim',
                        'Samples – usually more than one',
                        'For IPX8 only: the depth and duration you intend to declare',
                    ],
                ],
            ],
        ],
        'reviews' => [
            'heading' => 'What manufacturers say afterwards',
        ],
        'related' => [
            'heading' => 'Products we arrange IP testing for',
            'intro' => 'Also searched as IP testing lab, IP67 testing lab, IP rating test lab and IEC 60529 testing.',
            'keywords' => [
                'Street & flood lights', 'LED drivers', 'Control panels & junction boxes', 'EV chargers & connectors', 'Battery packs', 'Smartphones & wearables',
                'Earbuds', 'CCTV cameras', 'Solar junction boxes & inverters', 'Motors & sensors', 'Food processing equipment', 'Telecom cabinets',
            ],
        ],
        'faqs' => [
            'heading' => 'Questions we get on the first call',
            'items' => [
                ['question' => 'Is IP testing mandatory in India?', 'answer' => 'Not on its own. IP requirements sit inside other things – a product standard you certify to, a tender, a buyer’s purchase condition or a marketplace’s evidence policy. Which one applies also decides which lab the report must come from.'],
                ['question' => 'What rating does my product need?', 'answer' => 'Work back from where it lives. Indoors, dust only – IP5X or IP54. Outdoors in rain – IP65 or IP66. Could be dropped in water – IP67. Submerged – IP68 with a declared depth and time. Hosed with hot water – IPX9 or IP69K. Going higher than the duty needs just costs more.'],
                ['question' => 'How long does IP testing take?', 'answer' => 'The tests are short – the dust chamber runs for hours and most water tests for minutes. Lab queues and sample logistics set the calendar: plan one to two weeks once samples reach the lab.'],
                ['question' => 'Does an IP rating expire?', 'answer' => 'The report doesn’t expire by itself. Changing the enclosure, gasket, gland, vent, fasteners or tool means it no longer describes what you sell, and the rating should be retested.'],
                ['question' => 'Can one product carry two ratings?', 'answer' => 'Yes. IPX7 and IPX8 don’t include the jet tests, so a product that must survive both a hose and immersion is declared as a pair, such as IP66/IP68, with both tests run.'],
                ['question' => 'What does IP testing cost?', 'answer' => 'Two numbers, always shown separately: the lab charge, which depends on the rating, sample size and rigs; and our fee for the rating assessment, lab matching, sample review and managing the file. You see the lab’s figure before we book.'],
                ['question' => 'Can you test a prototype before we commit to tooling?', 'answer' => 'Yes, and for moulded enclosures we usually suggest it. A pre-compliance run is faster, cheaper and doesn’t have to be declared. If it fails, you learn where water gets in while the tool can still change.'],
                ['question' => 'Do you do the testing yourselves?', 'answer' => 'No. Instacertify is a testing marketplace and consultancy. Testing is done by independent accredited partner laboratories; we set the rating, choose the lab, prepare the sample and check the report.'],
            ],
        ],
        'closing' => [
            'heading' => 'Tell us the product. We’ll tell you the rating and the lab.',
            'text' => 'Send the model, roughly where it’s installed and who asked for the rating. You’ll get the rating, the lab and the cost.',
        ],
    ],
];
