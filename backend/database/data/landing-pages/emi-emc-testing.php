<?php

return [
    'title' => 'Google Ads – EMI EMC testing',
    'path' => 'lp/emi-emc-testing',
    'service_path' => 'services/testing/electronics-electricals-testing',
    'stat' => ['value' => 'NABL', 'label' => 'Accredited partner labs'],
    'seo' => [
        'title' => 'EMI / EMC Testing – CISPR & IEC 61000 | Instacertify',
        'description' => 'EMI and EMC testing for electronics, lighting, appliances, EV and telecom at accredited partner labs. Right standard first, pre-compliance, clear pricing.',
        'keyword' => 'EMC testing',
    ],
    'content' => [
        'hero' => [
            'headline' => 'EMI / EMC Testing – CISPR & IEC 61000 at Accredited Labs',
            'subheadline' => 'Emission and immunity testing for electronics, appliances, lighting, EV, telecom and industrial equipment. We fix the right standard first, then book a partner lab with that exact scope.',
            'points' => [
                'Emission (CISPR) and immunity (IEC 61000) planned together',
                'NABL partner labs, TEC-designated where MTCTE needs it',
                'Pre-compliance scan before the formal booking',
                'Lab charge and our fee shown separately',
            ],
            'price_note' => 'Lab charge and our fee shown separately, before anything is booked',
            'cta' => 'Get my EMC test plan',
            'form_title' => 'Get your EMC test plan',
            'form_subtitle' => 'Tell us the product and the markets. We’ll reply with the standards, tests and cost.',
        ],
        'options' => [
            'heading' => 'Who is asking you for an EMC report?',
            'intro' => 'The answer decides which laboratory can do the work – so it’s the first thing we establish.',
            'price_label' => 'Price',
            'items' => [
                [
                    'title' => 'TEC MTCTE & WPC',
                    'audience' => 'Telecom equipment and anything with a radio',
                    'timeline' => 'Planned with your approval',
                    'price' => 'Lab + our fee',
                    'points' => ['TEC-designated lab where required', 'EMC alongside the radio tests', 'One sample shipment'],
                ],
                [
                    'title' => 'Tenders & buyers',
                    'audience' => 'Lighting, metering, railway, defence and utility supply',
                    'timeline' => '1–3 weeks at the lab',
                    'price' => 'Lab + our fee',
                    'points' => ['Tender clause read before quoting', 'Report in the bidder’s name', 'Right standard and accreditation'],
                ],
                [
                    'title' => 'Export – CE, FCC, UKCA',
                    'audience' => 'EU EMC Directive, US FCC, UK and Gulf markets',
                    'timeline' => 'One campaign',
                    'price' => 'Lab + our fee',
                    'points' => ['Several markets planned into one campaign', 'Product-family standard, not a generic one', 'Report ready for your technical file'],
                ],
            ],
        ],
        'compare' => [
            'heading' => 'Your BIS certificate almost certainly doesn’t cover EMC',
            'intro' => 'CRS registration is granted against a safety standard. Passing it says nothing about what your product radiates or what it survives. In India the EMC obligation arrives through TEC MTCTE, WPC ETA, automotive rules, a tender, a buyer or your export market.',
            'bad' => [
                'EMC thought about after the design is frozen',
                'Standard copied from a competitor’s certificate',
                'Radiated emission fails and nobody can say why',
                'A filter guessed at, the chamber rebooked, the launch slips',
            ],
            'good' => [
                'Standard, class and ports pinned down in writing first',
                'Pre-compliance scan while fixes are still cheap',
                'Lab matched to the scope, including TEC designation',
                'Every market planned into one campaign',
            ],
        ],
        'steps' => [
            'heading' => 'How it works',
            'items' => [
                ['title' => 'We fix the standard', 'text' => 'Product family, class, ports, environment and markets – in writing.'],
                ['title' => 'Test plan & criteria', 'text' => 'Tests, levels and pass criteria agreed before the lab is approached.'],
                ['title' => 'Pre-compliance', 'text' => 'A short scan where it’s worth it – we’ll say when it isn’t.'],
                ['title' => 'Lab booking & testing', 'text' => 'Scope, chamber and availability matched to your plan.'],
                ['title' => 'Report & next steps', 'text' => 'Report checked against what your buyer or filing needs.'],
            ],
        ],
        'benefits' => [
            'heading' => 'Why manufacturers choose Instacertify',
            'items' => [
                ['icon' => 'rule', 'title' => 'Right standard first', 'text' => 'Where most EMC money is wasted – and where we start.'],
                ['icon' => 'radar', 'title' => 'Pre-compliance scans', 'text' => 'Find the problem while a ferrite or filter still fixes it.'],
                ['icon' => 'science', 'title' => 'Accredited partner labs', 'text' => 'NABL labs, TEC-designated where MTCTE requires it.'],
                ['icon' => 'troubleshoot', 'title' => 'Failure diagnosis', 'text' => 'We read the plots with you and find the source.'],
            ],
        ],
        'checklist' => [
            'heading' => 'The product, its ports and every market',
            'intro' => 'Telling us about the European order after the India testing is booked usually means paying for two campaigns that could have been one.',
            'groups' => [
                [
                    'title' => 'Your checklist',
                    'items' => [
                        'Product name, model and a photograph',
                        'Port list – mains, DC, signal, Ethernet, antenna, USB – and cable lengths',
                        'Power rating and supply type',
                        'Any radio inside – module name, bands, certified or not',
                        'Where it gets installed – home, office, factory, vehicle',
                        'Every market it’s going to, including the maybes',
                        'Samples and any software needed to run them',
                    ],
                ],
            ],
        ],
        'reviews' => [
            'heading' => 'What manufacturers say afterwards',
        ],
        'related' => [
            'heading' => 'Products we plan EMC testing for',
            'intro' => 'Also searched as EMI testing, EMC testing lab, EMC test report, CISPR 32 testing and IEC 61000 testing.',
            'keywords' => [
                'LED lighting (CISPR 15)', 'IT & multimedia (CISPR 32)', 'Household appliances (CISPR 14-1)', 'Industrial equipment (CISPR 11)',
                'Automotive electronics (CISPR 25)', 'Telecom equipment', 'EV chargers', 'Medical devices (IEC 60601-1-2)',
                'Power supplies & SMPS', 'Solar inverters', 'Smart meters', 'IoT devices',
            ],
        ],
        'faqs' => [
            'heading' => 'Questions we get on the first call',
            'items' => [
                ['question' => 'Is EMC testing mandatory in India?', 'answer' => 'There’s no single EMC law for every product. Telecom equipment picks it up through TEC MTCTE, wireless products alongside WPC ETA, automotive parts through AIS. Otherwise it comes from a tender, a buyer or your export market. Which applies also decides which lab can test.'],
                ['question' => 'Doesn’t my BIS certificate already cover EMC?', 'answer' => 'For most CRS products, no. CRS registration is granted against safety standards, which deal with shock, fire and mechanical hazards – not what your product radiates or withstands.'],
                ['question' => 'What’s the difference between EMI and EMC?', 'answer' => 'EMI is the interference itself; “EMI testing” usually means measuring emissions. EMC covers emissions plus immunity – what happens when something else interferes with your product. A complete campaign needs both.'],
                ['question' => 'How long does EMC testing take?', 'answer' => 'Plan for one to three weeks in the lab once samples arrive. With sample logistics and the report, a straightforward campaign is typically three to five weeks end to end. We quote the realistic date, not the optimistic one.'],
                ['question' => 'What does EMC testing cost?', 'answer' => 'Two numbers, always shown separately: the lab charge, which depends on the standard, the number of tests and chamber time; and our fee for the standard, test plan, lab matching and file management. You see the lab’s figure before we book.'],
                ['question' => 'My product failed radiated emission. What now?', 'answer' => 'Get the plot and the frequency – it usually points to the source: switching-supply harmonics, a clock coupling onto a cable, or a poorly bonded shield. We go through the plots with you, agree the fix, and tell you whether a partial retest will cover it.'],
                ['question' => 'Can one report cover several countries?', 'answer' => 'Often, if planned that way. CISPR-based requirements underpin the EU, UK and much of Asia, and FCC can usually run in the same lab visit. India’s TEC and WPC routes prescribe where testing happens, so they’re planned around.'],
                ['question' => 'Do you do the testing yourselves?', 'answer' => 'No. Instacertify is a testing marketplace and consultancy. Testing is done by independent accredited partner laboratories; we set the standard and test plan, book the lab and check the report says what you need.'],
            ],
        ],
        'closing' => [
            'heading' => 'Tell us the product. We’ll tell you the standard and the lab.',
            'text' => 'Send the model, its ports and every market. You’ll get the standard, the test plan, the lab and the cost – lab charge and our fee shown separately.',
        ],
    ],
];
