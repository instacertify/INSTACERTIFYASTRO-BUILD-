<?php

return [
    'title' => 'CISPR 32 & EMC Immunity Tests Explained: Limits for Cameras, Audio-Video and IT Equipment',
    'category' => 'global-compliance',
    'author' => 'compliance-desk',
    'seo' => [
        'title' => 'CISPR 32 & EMC Immunity Testing Explained (2026)',
        'description' => 'CISPR 32 emission classes, CISPR 35 immunity and IEC 61000-4 tests explained for cameras, audio and video equipment: what is tested and what drives lab cost.',
        'keyword' => 'cispr 32, cispr camera, cispr audio, cispr video system, emc immunity test, emi immunity test',
    ],
    'faqs' => [
        ['q' => 'What is CISPR 32?', 'a' => 'CISPR 32 is the international standard for electromagnetic emissions from multimedia equipment, which includes IT, audio, video, broadcast receiver and similar equipment. It replaced the older CISPR 13 and CISPR 22.'],
        ['q' => 'What is the difference between Class A and Class B in CISPR 32?', 'a' => 'Class B equipment is intended for residential use and must meet tighter emission limits. Class A equipment is intended for commercial or industrial use only and carries a warning that it may cause interference in homes.'],
        ['q' => 'Does CISPR 32 cover immunity?', 'a' => 'No. CISPR 32 covers emissions only. Immunity for multimedia equipment is covered by CISPR 35, which uses the IEC 61000-4 test methods.'],
        ['q' => 'Which standard applies to CCTV cameras?', 'a' => 'Emissions are usually tested to CISPR 32. For immunity, CISPR 35 applies to most cameras, and cameras used in alarm and security systems are often tested to EN 50130-4 as well.'],
        ['q' => 'What are the common EMC immunity tests?', 'a' => 'Electrostatic discharge (IEC 61000-4-2), radiated RF immunity (4-3), electrical fast transients (4-4), surge (4-5), conducted RF immunity (4-6), power-frequency magnetic field (4-8) and voltage dips and interruptions (4-11).'],
        ['q' => 'How long does EMC testing take?', 'a' => 'Emission and immunity testing for one product usually takes a few days of lab time. With booking, report writing and any fixes after a failure, plan for one to three weeks.'],
    ],
    'content' => <<<'HTML'
<p>If you make cameras, speakers, TVs, set-top boxes, monitors or any IT product, two standards decide whether your product passes EMC: <strong>CISPR 32</strong> for emissions and <strong>CISPR 35</strong> for immunity. Together they replaced a patchwork of older standards and are now the reference for multimedia equipment worldwide.</p>
<p>This guide explains what each standard covers, how Class A and Class B work, which immunity tests apply and what affects lab time and cost.</p>

<h2>What Is CISPR 32?</h2>
<p><strong>CISPR 32</strong> sets limits for electromagnetic <strong>emissions</strong> from <strong>multimedia equipment (MME)</strong>. MME includes:</p>
<ul>
<li>IT equipment: computers, monitors, printers, network equipment</li>
<li>Audio equipment: speakers, amplifiers, soundbars</li>
<li>Video equipment: TVs, cameras, projectors, set-top boxes</li>
<li>Combinations, such as smart displays and video doorbells</li>
</ul>
<p>It replaced <strong>CISPR 13</strong> (broadcast receivers) and <strong>CISPR 22</strong> (IT equipment). If your old reports cite those, they are out of date for most markets.</p>

<h2>Class A vs Class B</h2>
<table>
<thead><tr><th></th><th>Class A</th><th>Class B</th></tr></thead>
<tbody>
<tr><td>Intended environment</td><td>Commercial and industrial</td><td>Residential</td></tr>
<tr><td>Limits</td><td>Less strict</td><td>Stricter</td></tr>
<tr><td>Warning required</td><td>Yes, that the product may cause interference in a home</td><td>No</td></tr>
<tr><td>Examples</td><td>Server racks, industrial displays</td><td>Home cameras, speakers, TVs, laptops</td></tr>
</tbody>
</table>
<p>Most consumer products must meet <strong>Class B</strong>. Do not choose Class A just because it is easier to pass, if the product will be used in homes.</p>

<h2>What CISPR 32 Tests</h2>
<ul>
<li><strong>Conducted emissions</strong> on AC mains ports, from 150 kHz to 30 MHz</li>
<li><strong>Conducted emissions on wired network and telecom ports,</strong> such as Ethernet</li>
<li><strong>Radiated emissions</strong> from 30 MHz to 1 GHz, and above 1 GHz up to 6 GHz depending on the highest internal frequency</li>
</ul>
<p>Common causes of failure are switching power supplies, unshielded cables, poor PCB grounding and high-speed clocks.</p>

<h2>CISPR 35 and EMC Immunity Tests</h2>
<p><strong>CISPR 35</strong> covers <strong>immunity</strong> of multimedia equipment and replaced CISPR 20 and CISPR 24. It uses the <strong>IEC 61000-4 series</strong> test methods:</p>
<table>
<thead><tr><th>Test</th><th>Standard</th><th>What it simulates</th></tr></thead>
<tbody>
<tr><td>Electrostatic discharge</td><td>IEC 61000-4-2</td><td>A user touching the product</td></tr>
<tr><td>Radiated RF immunity</td><td>IEC 61000-4-3</td><td>Nearby radios and phones</td></tr>
<tr><td>Electrical fast transients</td><td>IEC 61000-4-4</td><td>Switching of loads on the same supply</td></tr>
<tr><td>Surge</td><td>IEC 61000-4-5</td><td>Lightning and large switching events</td></tr>
<tr><td>Conducted RF immunity</td><td>IEC 61000-4-6</td><td>RF picked up on cables</td></tr>
<tr><td>Power-frequency magnetic field</td><td>IEC 61000-4-8</td><td>Fields near transformers and power lines</td></tr>
<tr><td>Voltage dips and interruptions</td><td>IEC 61000-4-11</td><td>Brownouts and short power cuts</td></tr>
</tbody>
</table>
<p>Our guide to <a href="/resources/blogs/iec-standards-used-emc-testing-guide-emc-immunity-test">IEC standards used in EMC immunity testing</a> covers each test method in more detail.</p>

<h2>CISPR 32 for Cameras, Audio and Video Systems</h2>
<ul>
<li><strong>CCTV and IP cameras:</strong> CISPR 32 for emissions and CISPR 35 for immunity. Cameras in alarm and security systems are often also tested to EN 50130-4.</li>
<li><strong>Audio equipment:</strong> CISPR 32 and CISPR 35. Immunity checks include whether audio output degrades during RF tests.</li>
<li><strong>Video systems and displays:</strong> CISPR 32 and CISPR 35. Picture disturbance is judged against the performance criteria in the standard.</li>
</ul>
<p>Products with radios also need radio EMC standards such as the ETSI EN 301 489 series for Europe. See our <a href="/resources/blogs/wireless-device-testing-india-rf-sar-emc">wireless device testing guide</a>.</p>

<h2>Where CISPR 32 Is Required</h2>
<ul>
<li><strong>European Union and UK:</strong> EN 55032 and EN 55035 under the EMC Directive or UK EMC regulations</li>
<li><strong>United States:</strong> FCC Part 15B uses its own limits, closely aligned with CISPR 32 for many products</li>
<li><strong>India:</strong> TEC requirements for telecom equipment reference CISPR 32 for EMC, and buyers often ask for it</li>
<li><strong>Many other markets</strong> that adopt IEC and CISPR standards</li>
</ul>

<h2>Lab Time and Cost</h2>
<p>EMC lab cost depends on:</p>
<ul>
<li>The number of ports (mains, Ethernet, USB, audio and video)</li>
<li>Whether radiated emissions above 1 GHz are needed</li>
<li>The number of modes the product must be tested in</li>
<li>Pre-compliance and debugging time if the product fails</li>
</ul>
<p>Testing a single product usually takes a few days. With booking and report writing, plan for <strong>one to three weeks</strong>. Pre-compliance scans early in design save the most time.</p>

<h2>Common Mistakes</h2>
<ul>
<li><strong>Testing to CISPR 22 or CISPR 24.</strong> These are withdrawn for most markets.</li>
<li><strong>Wrong class.</strong> Home products must meet Class B.</li>
<li><strong>Unrepresentative cables.</strong> Test with the cables you ship.</li>
<li><strong>Skipping performance criteria.</strong> Define what "normal operation" means before immunity testing.</li>
</ul>

<h2>How Instacertify Helps</h2>
<p>We plan EMC tests for your target markets, run pre-compliance, coordinate testing in accredited labs and help fix failures. See our <a href="/cispr-32">CISPR 32 testing</a> page and <a href="/services/testing/electronics-electricals-testing">electronics and electrical testing services</a>.</p>
HTML,
];
