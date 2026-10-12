<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LandingPage::firstOrCreate(['path' => 'lp/bis-certification'], [
            'title' => 'Google Ads – BIS certification (ISI & CRS)',
            'status' => 'published',
            'indexable' => false,
            'service_path' => 'bis-certification',
            'seo' => [
                'title' => 'BIS Certification – ISI Mark & CRS | Instacertify',
                'description' => 'ISI Mark and CRS registration handled end to end. Itemised quote naming the lab, testing charge and BIS fees. Consulting from ₹9,999.',
                'keyword' => 'BIS certification',
            ],
            'content' => [
                'hero' => [
                    'headline' => 'BIS Certification for Your Product – ISI Mark & CRS',
                    'subheadline' => 'Find out whether your product needs BIS, which route applies and what it will cost in total. We then handle documents, lab testing and filing until the licence is granted.',
                    'points' => [
                        'Itemised quote: lab, testing charge, BIS fees and our fee',
                        'Indian manufacturers and foreign brands',
                        'Offices in Noida, Vadodara and Kalyan',
                    ],
                    'price_note' => 'Consulting from ₹9,999 for CRS · ₹20,999 for ISI Mark',
                    'reply_note' => 'We reply within 30 minutes',
                    'cta' => 'Get my free BIS quote',
                    'form_title' => 'Get your BIS quote',
                    'form_subtitle' => 'Tell us the product. We’ll reply with the scheme, standard, testing cost and timeline.',
                ],
                'stats' => [
                    ['value' => '42,818+', 'label' => 'Happy customers'],
                    ['value' => '15+', 'label' => 'Expert advisors'],
                    ['value' => '3', 'label' => 'Offices in India'],
                    ['value' => 'Free', 'label' => 'Scheme check'],
                ],
                'options' => [
                    'heading' => 'ISI Mark or CRS – which one do you need?',
                    'intro' => 'It depends on what the product is, not on which route sounds easier. We confirm it before quoting.',
                    'items' => [
                        [
                            'title' => 'ISI Mark',
                            'audience' => 'Manufacturers in India – steel, cement, appliances, footwear, chemicals',
                            'timeline' => '3–6 months',
                            'price' => 'From ₹20,999',
                            'points' => ['Testing at a BIS-recognised lab', 'Factory inspection by BIS', 'Licence to use the ISI mark'],
                        ],
                        [
                            'title' => 'CRS registration',
                            'audience' => 'Electronics and IT goods made in India or abroad – chargers, LED lights, power banks, laptops',
                            'timeline' => '4–6 weeks',
                            'price' => 'From ₹9,999',
                            'points' => ['Testing at a BIS-recognised lab', 'Online filing on the BIS portal', 'Indian representative arranged for foreign brands'],
                        ],
                    ],
                ],
                'steps' => [
                    'heading' => 'How it works',
                    'items' => [
                        ['title' => 'Free scheme check', 'text' => 'We confirm the route, the Indian Standard and whether a QCO applies.'],
                        ['title' => 'Itemised quote', 'text' => 'Lab, testing charge, BIS fees and our fee – in writing, before you pay.'],
                        ['title' => 'Testing & filing', 'text' => 'We book the lab, track your samples and file on the BIS portal.'],
                        ['title' => 'Licence granted', 'text' => 'Then we handle renewals, model additions and changes.'],
                    ],
                ],
                'benefits' => [
                    'heading' => 'Why manufacturers choose Instacertify',
                    'items' => [
                        ['icon' => 'receipt_long', 'title' => 'No “at actual” surprises', 'text' => 'The lab and the testing charge are named in your quote.'],
                        ['icon' => 'verified', 'title' => 'Honest advice', 'text' => 'If your product doesn’t need BIS, we tell you.'],
                        ['icon' => 'build_circle', 'title' => 'Stuck mid-process?', 'text' => 'Queried application or failed samples – we’ll tell you what it needs.'],
                        ['icon' => 'event_repeat', 'title' => 'Renewals tracked', 'text' => 'We flag renewals before your licence lapses.'],
                    ],
                ],
                'related' => [
                    'heading' => 'Products we certify under BIS',
                    'intro' => 'Looking for BIS registration, an ISI mark licence or a CRS R-number? It’s the same team and the same free check.',
                    'keywords' => [
                        'LED lights & drivers', 'Mobile chargers & adaptors', 'Power banks', 'Laptops & tablets',
                        'CCTV cameras', 'Smart watches', 'Steel products', 'Cement',
                        'Electric irons & heaters', 'Toys', 'Footwear', 'Plywood',
                        'Kitchen appliances', 'Pressure cookers', 'Helmets', 'Packaged drinking water',
                    ],
                ],
                'reviews' => [
                    'heading' => 'What manufacturers say',
                ],
                'faqs' => [
                    'heading' => 'Common questions',
                    'items' => [
                        ['question' => 'Does my product need BIS certification?', 'answer' => 'It does if a Quality Control Order covers the Indian Standard for your product. Send us the product name or HSN code and we’ll confirm it free – including when a QCO is notified but not yet in force.'],
                        ['question' => 'What is the difference between ISI and CRS?', 'answer' => 'ISI Mark is for manufacturers in India making products such as steel, cement, footwear and appliances, and includes a factory inspection. CRS is registration for electronics and IT goods, made in India or abroad.'],
                        ['question' => 'How much does BIS certification cost?', 'answer' => 'There are three parts: BIS’s own fees, lab testing and our fee. Consulting starts at ₹9,999 for CRS and ₹20,999 per product for ISI. Our quote names the lab and the testing charge, so nothing is left “at actual”.'],
                        ['question' => 'How long does it take?', 'answer' => 'Typically 4–6 weeks for CRS and 3–6 months for ISI, including testing. We give you a realistic date for your product on the first call.'],
                        ['question' => 'Can a foreign brand get CRS registration?', 'answer' => 'Yes. Electronics and IT goods made abroad are registered under CRS through an Authorised Indian Representative. We arrange the representative and handle the filing.'],
                        ['question' => 'Do you do the testing yourselves?', 'answer' => 'No. BIS testing must be done at a BIS-recognised laboratory. We book the slot, manage the samples and handle any retests.'],
                    ],
                ],
                'closing' => [
                    'heading' => 'Find out where your product stands – free',
                    'text' => 'Send the product name. We’ll reply with the scheme, standard, cost and timeline, usually within 30 minutes.',
                ],
            ],
        ]);
    }

    public function down(): void
    {
        LandingPage::where('path', 'lp/bis-certification')->delete();
    }
};
