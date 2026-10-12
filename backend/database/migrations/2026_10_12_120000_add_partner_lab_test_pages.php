<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const INCLUDES = [
        'Lab and parameter list chosen for your purpose: licence, export, buyer or internal QC',
        'Sampling guidance, and pickup or on-site sampling where needed',
        'One contact who follows up with the lab until the report is issued',
        'Report explained in plain language, with next steps if a result fails',
    ];

    private const DETAILS = [
        'food' => [
            'Product name, FSSAI category and licence or registration number, if you have one',
            'Ingredient list or recipe, and the label artwork',
            'Purpose of testing: FSSAI licence, periodic testing, export, buyer or internal QC',
            'For export: destination country and the buyer’s specification',
        ],
        'environment' => [
            'Consent to operate or environmental clearance conditions, if you have them',
            'A short description of the process and the sources to be checked',
            'Purpose: SPCB return, consent renewal, audit, buyer or complaint',
            'Previous test reports, if any',
        ],
        'chemical' => [
            'Product name, grade and specification sheet',
            'Safety data sheet (SDS)',
            'The standard or buyer specification to test against',
            'Purpose: quality check, dispute, export or regulatory filing',
        ],
        'cosmetic' => [
            'Product name and ingredient list (INCI)',
            'Label and the claims you plan to make',
            'Markets where the product will be sold',
            'Cosmetic licence or import registration number, if any',
        ],
        'health' => [
            'Product name, composition and licence category (AYUSH, CDSCO or FSSAI)',
            'Label and claims',
            'The pharmacopoeia, standard or buyer specification to test against',
            'Purpose: licence, tender, export or internal QC',
        ],
        'material' => [
            'Product name, material or grade and intended use',
            'Drawings or specifications, where relevant',
            'The standard or buyer specification to test against',
            'Markets where the product will be sold',
        ],
        'electrical' => [
            'Model list with ratings, and the series or family you want covered',
            'Circuit diagram, critical components list and user manual',
            'The Indian Standard or IEC standard that applies',
            'BIS registration or licence number, if you already have one',
        ],
        'device' => [
            'Device name, intended use and risk class under the Medical Device Rules',
            'Materials, parts that contact the patient and duration of contact',
            'Instructions for use, labels and any existing test reports',
            'Markets where the device will be sold',
        ],
        'workplace' => [
            'Site address, type of facility and the areas or systems to be checked',
            'Number of people or the process at each location',
            'Purpose: statutory compliance, audit, certification, complaint or internal check',
            'Previous monitoring reports, if any',
        ],
    ];

    private const ACCEPTANCE = [
        'food' => [
            'q' => 'Will FSSAI and buyers accept the report?',
            'a' => 'Tests are done in labs accredited by NABL for the parameters reported, and for regulatory use in FSSAI-notified labs, which is what FSSAI, food safety officers and most buyers expect. For export, we match the lab to the importing country’s or buyer’s requirement.',
        ],
        'environment' => [
            'q' => 'Will the Pollution Control Board accept the report?',
            'a' => 'Monitoring is done by labs accredited by NABL and recognised under the Environment (Protection) Act, which State Pollution Control Boards accept for consent compliance and returns. Some boards keep their own list of approved labs; we check yours before booking.',
        ],
        'chemical' => [
            'q' => 'Which standard is the product tested against?',
            'a' => 'The Indian Standard, international method or buyer specification that fits your purpose. We confirm it with you before the sample goes to the lab, and the report states the method used for each parameter.',
        ],
        'cosmetic' => [
            'q' => 'Can the report support CDSCO files and label claims?',
            'a' => 'Yes. Reports from NABL-accredited labs are used in cosmetic licence and import registration files, and as evidence for the claims on your label. We confirm what your application or retailer asks for before testing.',
        ],
        'health' => [
            'q' => 'Will regulators and buyers accept the report?',
            'a' => 'Testing is done in NABL-accredited labs, and where a licence requires it, in labs approved under the Drugs and Cosmetics Rules. We confirm the requirement for your licence, tender or buyer before testing.',
        ],
        'material' => [
            'q' => 'Which standard is the product tested against?',
            'a' => 'The Indian Standard, international standard or buyer specification that applies to your product and market. We confirm it with you before testing, and the report states the method used for each parameter.',
        ],
        'electrical' => [
            'q' => 'Will BIS accept the report?',
            'a' => 'For BIS registration and licences, testing is done at a lab recognised by BIS for that standard, and BIS accepts reports only from such labs. For tenders and buyers, a NABL-accredited lab report is usually enough. We confirm which applies before booking.',
        ],
        'device' => [
            'q' => 'Will CDSCO accept the report?',
            'a' => 'Testing is done at labs accredited by NABL for the standard, or registered under the Medical Device Rules where your application needs it. We confirm what your licence application, tender or export market asks for before testing.',
        ],
        'workplace' => [
            'q' => 'Will auditors and inspectors accept the report?',
            'a' => 'Monitoring is done by NABL-accredited labs using recognised Indian and international methods, which factory inspectors, green building assessors and customer auditors accept. Where your state rules name specific agencies, we check this before booking.',
        ],
    ];

    public function up(): void
    {
        // The pages link to the imported service catalogue; a fresh install has none.
        if (! Service::exists()) {
            return;
        }

        $sort = (int) Service::max('sort');
        $linkable = [...array_map($this->path(...), $this->pages()), ...Service::pluck('path')];

        foreach ($this->pages() as $page) {
            $page['related'] = array_values(array_intersect($page['related'], $linkable));
            $path = $this->path($page);
            if (Service::where('path', $path)->exists()) {
                continue;
            }

            Service::create([
                'path' => $path,
                'slug' => $page['slug'],
                'title' => $page['title'],
                'kind' => 'testing',
                'region' => null,
                'group' => $page['group'],
                'test_categories' => array_values(array_unique([$page['category'], ...($page['categories'] ?? [])])),
                'summary' => $page['summary'],
                'detail' => $this->detail($page),
                'seo' => ['title' => $page['seo_title'], 'description' => $page['seo_description']],
                'is_published' => true,
                'sort' => ++$sort,
            ]);
        }
    }

    public function down(): void
    {
        Service::whereIn('path', array_map($this->path(...), $this->pages()))->delete();
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        return array_merge(...array_map(fn (string $file) => require $file, File::glob(database_path('data/testing-services/*.php'))));
    }

    /** @param  array<string, mixed>  $page */
    private function path(array $page): string
    {
        return "services/testing/{$page['category']}/{$page['slug']}";
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function detail(array $page): array
    {
        $onsite = $page['onsite'] ?? false;

        return [
            'lead' => $page['lead'],
            'keyFacts' => [
                ['label' => 'Tested by', 'value' => $page['lab'] ?? 'NABL-accredited partner lab'],
                ['label' => 'Regulation', 'value' => $page['regulation']],
                ['label' => 'Typical turnaround', 'value' => $page['turnaround']],
                ['label' => $onsite ? 'Sampling' : 'Sample needed', 'value' => $page['sample']],
            ],
            'body' => $page['body'],
            'includes' => self::INCLUDES,
            'headings' => ['documents' => 'What to send', 'products' => 'Parameters'],
            'intros' => [
                'documents' => $onsite
                    ? 'We plan the visit with you, so that monitoring happens under normal operating conditions.'
                    : 'The lab confirms the exact quantity and packing for your product when we book the test.',
            ],
            'documents' => [
                $onsite
                    ? ['title' => 'For the site visit', 'items' => [
                        'Site address, a contact person and any entry permissions',
                        'Safe access to the sampling or monitoring points',
                        'A power point near the monitoring location',
                        'Plant or equipment running at normal load',
                    ]]
                    : ['title' => 'Sample', 'items' => [
                        "Quantity: {$page['sample']}",
                        'Sealed and labelled with the product name, batch number and date',
                        match ($page['domain']) {
                            'food' => 'Kept the way the product is normally stored: chilled, frozen or at room temperature',
                            'electrical', 'device', 'material' => 'Packed securely so that it is not damaged in transit',
                            default => 'Packed so that it cannot leak or be contaminated in transit',
                        },
                    ]],
                ['title' => 'Details to share', 'items' => self::DETAILS[$page['domain']]],
            ],
            'table' => [
                'title' => 'What the lab tests',
                'note' => 'The final list depends on your product, its standard and the purpose of testing. We confirm it with you before the sample goes to the lab.',
                'columns' => ['Parameter', 'Why it is checked'],
                'rows' => $page['parameters'],
            ],
            'faqs' => [
                ...$page['faqs'],
                self::ACCEPTANCE[$page['domain']],
                [
                    'q' => 'Who carries out the testing?',
                    'a' => $page['who'] ?? 'Instacertify’s partner laboratories, accredited by NABL to ISO/IEC 17025 for the parameters on your report. We choose the lab, arrange the sample, follow up until the report is issued and explain the results to you.',
                ],
            ],
            'related' => $page['related'],
        ];
    }
};
