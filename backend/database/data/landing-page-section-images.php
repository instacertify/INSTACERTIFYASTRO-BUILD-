<?php

/*
 * Illustrative photos further down each Google Ads landing page (files in landing-images/sections/).
 * Editors replace them under Landing pages → About the service, Why choose us and Documents.
 */
$images = [
    'intro-bis' => 'Engineer checking a test report beside an electric kettle, a motor and a coil of cable on a lab bench',
    'intro-emc' => 'Antenna and spectrum analyser measuring a device on a turntable in an EMC test chamber',
    'intro-epr' => 'Plastic packaging sorted into bottles, containers, pouches and film at a recycling facility',
    'intro-food-packaging' => 'Lab technician placing strips of a food container into beakers of food simulant',
    'intro-g-mark' => 'Iron, hair dryer and toaster wired to a power meter during safety testing',
    'intro-imei' => 'Smartphone with its SIM tray open beside SIM cards and an ejector pin',
    'intro-ip' => 'LED light fitting inside a dust test chamber',
    'intro-iso' => 'Quality manager holding a tablet with a process flow chart on a factory floor',
    'intro-lmpc' => 'Checking the label panel of a packaged food pouch with a magnifying glass',
    'intro-msds' => 'Chemist holding a sample bottle beside a laptop showing a safety data sheet',
    'intro-nutrition' => 'Food samples and an analytical balance in a food testing laboratory',
    'intro-saber' => 'Inspector with a tablet checking pallets of electrical goods and cable in an export warehouse',
    'intro-soncap' => 'Crane loading a container onto a cargo ship at a port',
    'checklist-documents' => 'Product documents, a technical drawing and a product sample laid out on a desk',
    'checklist-samples' => 'Product samples in boxes and sealed bags at a testing laboratory reception',
    'checklist-formulation' => 'Ingredient list, analysis report and three product samples on a desk',
    'benefits-office' => 'Compliance consultant’s desk with project tracking spreadsheets',
    'benefits-lab' => 'Product testing laboratory with test instruments and a climatic chamber',
];

/** Landing page => [About photo, Why choose us photo, Documents photo]. */
$pages = [
    'lp/bis-certification' => ['intro-bis', 'benefits-office', 'checklist-documents'],
    'lp/emi-emc-testing' => ['intro-emc', 'benefits-lab', 'checklist-samples'],
    'lp/epr-registration' => ['intro-epr', 'benefits-office', 'checklist-documents'],
    'lp/food-packaging-migration-testing' => ['intro-food-packaging', 'benefits-lab', 'checklist-samples'],
    'lp/g-mark-certification' => ['intro-g-mark', 'benefits-office', 'checklist-documents'],
    'lp/imei-certificate-for-import' => ['intro-imei', 'benefits-office', 'checklist-documents'],
    'lp/imei-certification' => ['intro-imei', 'benefits-office', 'checklist-documents'],
    'lp/imei-registration-for-manufacturers' => ['intro-imei', 'benefits-office', 'checklist-documents'],
    'lp/ingress-protection-testing' => ['intro-ip', 'benefits-lab', 'checklist-samples'],
    'lp/iso-certification' => ['intro-iso', 'benefits-office', 'checklist-documents'],
    'lp/legal-metrology-registration' => ['intro-lmpc', 'benefits-office', 'checklist-documents'],
    'lp/lmpc-certificate' => ['intro-lmpc', 'benefits-office', 'checklist-documents'],
    'lp/msds-authoring' => ['intro-msds', 'benefits-office', 'checklist-formulation'],
    'lp/msds-for-amazon' => ['intro-msds', 'benefits-office', 'checklist-formulation'],
    'lp/msds-for-export' => ['intro-msds', 'benefits-office', 'checklist-formulation'],
    'lp/msds-for-tender' => ['intro-msds', 'benefits-office', 'checklist-formulation'],
    'lp/nutritional-value-testing' => ['intro-nutrition', 'benefits-lab', 'checklist-samples'],
    'lp/saber-certification' => ['intro-saber', 'benefits-office', 'checklist-documents'],
    'lp/soncap-certification' => ['intro-soncap', 'benefits-office', 'checklist-documents'],
];

return ['images' => $images, 'pages' => $pages];
