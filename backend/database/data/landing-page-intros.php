<?php

/* "About the service" explainers for the Google Ads landing pages, keyed by page path. */

return [
    'lp/bis-certification' => [
        'heading' => 'What is BIS certification?',
        'text' => "BIS certification shows that a product meets the Indian Standard that applies to it. For products under a Quality Control Order it is mandatory: they cannot be made, imported or sold in India without it.\n\nThere are two main routes. The ISI mark covers products such as steel, cement, appliances and toys, and includes a factory audit. CRS registration covers electronics and IT products and is based on testing in a BIS-recognised lab.",
        'points' => [
            'Mandatory for products under a Quality Control Order',
            'ISI mark: product testing plus a factory audit',
            'CRS registration: testing in a BIS-recognised lab',
            'Licences are issued only by the Bureau of Indian Standards',
            'We confirm your route and the BIS fee before you commit',
        ],
    ],
    'lp/emi-emc-testing' => [
        'heading' => 'What is EMI/EMC testing?',
        'text' => "EMC testing checks two things: that your product doesn’t give off electromagnetic interference above set limits (emissions, or EMI), and that it keeps working when other equipment interferes with it (immunity).\n\nIt is needed for CE marking in Europe and FCC in the US, and Indian buyers, tenders and telecom approvals often ask for it. Testing is done in an accredited lab against the standard for your type of product.",
        'points' => [
            'Emissions: conducted and radiated',
            'Immunity: ESD, surge, fast transients and more',
            'Needed for CE, FCC and many tenders',
            'Pre-compliance scans catch failures before the formal test',
            'Reports from accredited partner labs',
        ],
    ],
    'lp/epr-registration' => [
        'heading' => 'What is EPR registration?',
        'text' => "Extended Producer Responsibility (EPR) makes producers, importers and brand owners responsible for collecting and recycling the waste their products leave behind.\n\nIn India you register on the CPCB portal, with separate rules for plastic packaging, e-waste, batteries and tyres. Once registered, you meet a yearly target with certificates from registered recyclers and file annual returns.",
        'points' => [
            'Applies to producers, importers and brand owners',
            'Separate rules for plastic, e-waste, batteries and tyres',
            'Registration on the CPCB portal',
            'Yearly targets met with recycler certificates',
            'Annual returns keep the registration in good standing',
        ],
    ],
    'lp/food-packaging-migration-testing' => [
        'heading' => 'What is food packaging migration testing?',
        'text' => "Migration testing measures how much of a packaging material’s substances move into the food it holds. The lab exposes the packaging to food simulants at a set time and temperature, then measures overall migration and, where needed, specific substances.\n\nFSSAI’s packaging regulations, BIS standards for food-grade plastics and export buyers all rely on these results.",
        'points' => [
            'Overall migration and specific migration',
            'Food simulants matched to the food you pack',
            'For plastics, coatings, paper and metal packaging',
            'Asked for under FSSAI rules, by BIS and by export buyers',
            'Tested in accredited partner labs',
        ],
    ],
    'lp/g-mark-certification' => [
        'heading' => 'What is the G-Mark?',
        'text' => "The G-Mark is the Gulf Conformity Mark for products sold in the GCC: Saudi Arabia, the UAE, Kuwait, Bahrain, Qatar, Oman and Yemen. It is mandatory for low-voltage electrical products and children’s toys covered by GSO technical regulations.\n\nA notified body reviews the test reports and issues the certificate, and each product carries the G-Mark with a QR code that customs and buyers can check.",
        'points' => [
            'Mandatory for low-voltage electrical products and toys',
            'Certificate issued by a GSO notified body',
            'Each product carries a traceable QR code',
            'Existing CB or IEC reports can often be reused',
            'Shipments to Saudi Arabia also need SABER',
        ],
    ],
    'lp/imei-certificate-for-import' => [
        'heading' => 'What is an IMEI certificate for import?',
        'text' => "Mobile phones and other IMEI-bearing devices imported into India must have their IMEI numbers registered on the ICDR portal run by the Department of Telecommunications.\n\nThe certificate from that portal lists the IMEI numbers in the consignment, and customs checks it before the shipment is cleared. Without it, the devices are held at the port.",
        'points' => [
            'Required for phones and other IMEI-bearing devices',
            'Issued on the Department of Telecommunications’ ICDR portal',
            'Checked by customs before clearance',
            'Phones also need BIS CRS registration',
            'We prepare the IMEI list and file it for each consignment',
        ],
    ],
    'lp/imei-certification' => [
        'heading' => 'What is IMEI certification?',
        'text' => "IMEI certification is the registration of a device’s IMEI numbers with the Department of Telecommunications on the ICDR portal. It is required for every mobile phone and IMEI-bearing device made in or imported into India.\n\nManufacturers register before the devices go on sale; importers register before customs clearance. It helps keep counterfeit and cloned devices off Indian networks.",
        'points' => [
            'For phones, tablets, trackers and other IMEI-bearing devices',
            'Registered on the Department of Telecommunications’ ICDR portal',
            'Needed by both manufacturers and importers',
            'Often filed alongside BIS CRS and WPC',
            'We prepare the IMEI list, documents and filing',
        ],
    ],
    'lp/imei-registration-for-manufacturers' => [
        'heading' => 'IMEI registration for manufacturers',
        'text' => "Phones and other IMEI-bearing devices made in India must have their IMEI numbers registered on the Department of Telecommunications’ ICDR portal before they are sold.\n\nAs a manufacturer you register your company on the portal once, then upload the IMEI numbers of each production batch. The numbers must come from your own GSMA allocation.",
        'points' => [
            'Mandatory for devices made in India',
            'Company registration once, IMEI upload for each batch',
            'IMEI numbers from your GSMA allocation',
            'Supports your BIS CRS and WPC filings',
            'We set up the portal account and manage the uploads',
        ],
    ],
    'lp/ingress-protection-testing' => [
        'heading' => 'What is IP (Ingress Protection) testing?',
        'text' => "IP testing shows how well an enclosure keeps out solid objects and water, rated to IEC 60529 (IS/IEC 60529 in India). The first digit covers dust and solids; the second covers water, from dripping to powerful jets and immersion.\n\nBuyers, tenders and BIS filings for lights, enclosures and outdoor products often ask for a specific rating such as IP65, IP67 or IP68.",
        'points' => [
            'Dust tests in a talc chamber',
            'Water tests from drips to jets and immersion',
            'Ratings from IP2X up to IP68 and IP69K',
            'Reports accepted by buyers, tenders and BIS filings',
            'Tested in accredited partner labs',
        ],
    ],
    'lp/iso-certification' => [
        'heading' => 'What is ISO certification?',
        'text' => "ISO certification shows that an independent certification body has audited your business against an ISO management system standard and found that it meets it.\n\nISO 9001 for quality management is the one most often asked for. ISO 14001 (environment), ISO 45001 (health and safety) and ISO 27001 (information security) are common in tenders and export contracts. Certificates run for three years, with a surveillance audit every year.",
        'points' => [
            'Audited by an accredited certification body',
            'ISO 9001, 14001, 45001, 27001 and more',
            'Three-year certificate with yearly surveillance audits',
            'Asked for in tenders and by export buyers',
            'We prepare your documents and get you audit-ready',
        ],
    ],
    'lp/legal-metrology-registration' => [
        'heading' => 'What is Legal Metrology registration?',
        'text' => "The Legal Metrology Act, 2009 regulates weights, measures and pre-packaged goods in India. Importers of pre-packaged goods and of weighing or measuring instruments must register with the Legal Metrology department, and packers need registration under the Packaged Commodities Rules.\n\nLabels must carry the declarations those rules require, including MRP, net quantity, the manufacturer’s or importer’s details and consumer care information.",
        'points' => [
            'Required for importers of pre-packaged goods',
            'Also for packers and for weighing and measuring instruments',
            'Labels must carry MRP, net quantity and contact details',
            'Filed with the Legal Metrology department',
            'We review your labels before filing',
        ],
    ],
    'lp/lmpc-certificate' => [
        'heading' => 'What is an LMPC certificate?',
        'text' => "LMPC stands for Legal Metrology Packaged Commodities. Rule 27 of the Legal Metrology (Packaged Commodities) Rules, 2011 requires manufacturers, packers and importers of pre-packaged goods to register, and the LMPC certificate is that registration.\n\nFor importers it is checked by customs at clearance, and online marketplaces ask for it before they list imported products.",
        'points' => [
            'Registration under Rule 27 of the Packaged Commodities Rules',
            'Needed by importers, manufacturers and packers',
            'Checked by customs and by online marketplaces',
            'Labels must carry the required declarations',
            'We review your labels and file the application',
        ],
    ],
    'lp/msds-authoring' => [
        'heading' => 'What is an MSDS?',
        'text' => "A Material Safety Data Sheet (MSDS), now usually called a Safety Data Sheet (SDS), describes the hazards of a chemical product and how to handle, store, transport and dispose of it safely.\n\nUnder the UN’s Globally Harmonized System (GHS) it has 16 fixed sections. Buyers, freight forwarders, airlines, ports and marketplaces ask for it before they accept the product.",
        'points' => [
            '16 sections in the GHS format',
            'Written from your formulation, under NDA',
            'Transport classification for sea, air and road',
            'Formats for India, the EU, the US, the GCC and more',
            'Updated when your formula or the rules change',
        ],
    ],
    'lp/msds-for-amazon' => [
        'heading' => 'Why Amazon asks for an MSDS',
        'text' => "Amazon reviews products that may be hazardous, such as items containing chemicals, batteries, aerosols or flammable liquids, before it lets them be listed or stored in its warehouses. As part of that review it asks for a Safety Data Sheet.\n\nThe sheet must match the product being listed, carry the right GHS classification and include transport information in Section 14.",
        'points' => [
            'Needed for chemicals, cosmetics, batteries, aerosols and more',
            'Product name that matches your listing',
            'GHS classification and Section 14 transport data',
            'Written from your formulation, under NDA',
            'Revised quickly if Amazon raises a query',
        ],
    ],
    'lp/msds-for-export' => [
        'heading' => 'Why exporters need an MSDS',
        'text' => "For export, the MSDS (or SDS) is the document that lets a chemical product move. Freight forwarders and shipping lines use it to classify the cargo for sea or air, customs at the destination may ask for it, and buyers check it against their own country’s rules, such as EU CLP or US OSHA.\n\nA sheet that doesn’t fit the destination is one of the most common reasons a shipment is held.",
        'points' => [
            'Transport classification for sea (IMDG) and air (IATA)',
            'Written to the destination’s format: EU, US, GCC and more',
            'Supplier and product details that match your invoice',
            'Written from your formulation, under NDA',
        ],
    ],
    'lp/msds-for-tender' => [
        'heading' => 'Why tenders ask for an MSDS',
        'text' => "Government and PSU tenders, including GeM bids, often list an MSDS as a mandatory document for chemicals, paints, lubricants, batteries and cleaning products.\n\nEvaluators check that it is in the 16-section GHS format, names your company as the supplier and matches the product you are bidding. A missing or generic sheet can disqualify an otherwise winning bid.",
        'points' => [
            'Often mandatory in GeM, PSU and government tenders',
            '16-section GHS format',
            'Your company named as the supplier',
            'Matches the exact product in your bid',
            'Written from your formulation, under NDA',
        ],
    ],
    'lp/nutritional-value-testing' => [
        'heading' => 'What is nutritional value testing?',
        'text' => "Nutritional value testing measures the energy, protein, carbohydrate, sugar, fat and other nutrients in a food product, so the values on its label are accurate.\n\nFSSAI’s labelling and display regulations require most packaged foods to show nutrition information per 100 g or 100 ml, and per serve. The lab report backs up every number on your pack.",
        'points' => [
            'Energy, protein, carbohydrates, sugars and fats',
            'Per 100 g or 100 ml and per serve',
            'Needed for FSSAI-compliant labels',
            'Extra parameters such as sodium, fibre or vitamins',
            'Tested in accredited partner labs',
        ],
    ],
    'lp/saber-certification' => [
        'heading' => 'What is SABER certification?',
        'text' => "SABER is Saudi Arabia’s online platform for product conformity, run by SASO. Products covered by Saudi technical regulations need a Product Certificate of Conformity (PCoC) for each model, and a Shipment Certificate (SCoC) for every consignment before it can clear Saudi customs.\n\nCertificates are issued through SABER by approved conformity assessment bodies.",
        'points' => [
            'Run by SASO, Saudi Arabia’s standards body',
            'Product certificate (PCoC) for each model',
            'Shipment certificate (SCoC) for every consignment',
            'Issued by approved conformity assessment bodies',
            'Some products also need an energy label or the G-Mark',
        ],
    ],
    'lp/soncap-certification' => [
        'heading' => 'What is SONCAP?',
        'text' => "SONCAP, the Standards Organisation of Nigeria Conformity Assessment Programme, checks that regulated products meet Nigerian standards before they are shipped.\n\nExporters first obtain a Product Certificate, then a SONCAP Certificate for each shipment, which the importer needs with Form M to clear Nigerian customs. Certificates are issued by SON-accredited certification bodies.",
        'points' => [
            'Required for regulated products shipped to Nigeria',
            'Product Certificate, then a SONCAP Certificate per shipment',
            'Needed with Form M for Nigerian customs',
            'Issued by SON-accredited certification bodies',
            'Pre-shipment inspection where required',
        ],
    ],
];
