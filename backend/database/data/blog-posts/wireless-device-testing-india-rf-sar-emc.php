<?php

return [
    'title' => 'Wireless Device Testing in India: RF, SAR, EMC & Safety Tests for WPC, TEC and FCC',
    'category' => 'global-compliance',
    'author' => 'compliance-desk',
    'seo' => [
        'title' => 'Wireless Device Testing India: RF, SAR & EMC Guide',
        'description' => 'Which tests a Bluetooth or Wi-Fi product needs before launch in India and abroad: RF, SAR, EMC and safety, and how results feed WPC, TEC and FCC approvals.',
        'keyword' => 'wireless device testing, wireless product testing, wireless testing, wireless testing provider',
    ],
    'faqs' => [
        ['q' => 'What tests does a wireless device need?', 'a' => 'Usually four groups: radio (RF) tests on the transmitter, RF exposure such as SAR for body-worn and handheld devices, EMC tests for emissions and immunity, and electrical safety tests.'],
        ['q' => 'Which approvals need wireless test reports in India?', 'a' => 'WPC ETA needs an RF test report for products using de-licensed bands. TEC certification under MTCTE needs testing in TEC-designated labs for notified telecom products. BIS CRS needs safety testing for notified electronics.'],
        ['q' => 'What is SAR testing?', 'a' => 'SAR, or Specific Absorption Rate, measures how much RF energy the body absorbs from a device. In India the limit for mobile phones is 1.6 W/kg averaged over 1 gram of tissue.'],
        ['q' => 'Can one set of tests be used for India, the EU and the US?', 'a' => 'Partly. Many tests overlap, but each market has its own standards and limits. A good test plan covers several markets in one lab visit to avoid repeat testing.'],
        ['q' => 'Does a pre-certified module remove the need for testing?', 'a' => 'It reduces it, but the final product usually still needs EMC, safety and some RF checks, because the enclosure, antenna and power supply can change performance.'],
        ['q' => 'How long does wireless testing take?', 'a' => 'Typically two to four weeks for RF, EMC and safety on one product, longer if SAR is needed or if the product fails and has to be fixed.'],
    ],
    'content' => <<<'HTML'
<p>Bluetooth speakers, smart watches, Wi-Fi cameras and IoT sensors all have one thing in common: before they can be sold, they must be <strong>tested as radio products</strong>. The tests feed approvals such as <strong>WPC ETA and TEC in India</strong>, <strong>FCC in the US</strong> and <strong>CE in Europe</strong>.</p>
<p>This guide explains the four groups of wireless testing, which approvals use them and how to plan testing so you do not pay twice.</p>

<h2>The Four Groups of Wireless Testing</h2>
<table>
<thead><tr><th>Test group</th><th>What it checks</th><th>Typical standards</th></tr></thead>
<tbody>
<tr><td>Radio (RF)</td><td>Output power, frequency, occupied bandwidth, spurious emissions, receiver performance</td><td>ETSI EN 300 328 and EN 301 893 (EU), FCC Part 15C and 15E (US)</td></tr>
<tr><td>RF exposure</td><td>SAR for devices used close to the body, or MPE calculation for others</td><td>IEC/IEEE 62209-1528, FCC KDB guidance</td></tr>
<tr><td>EMC</td><td>Emissions and immunity of the whole device</td><td>ETSI EN 301 489 series, CISPR 32 and CISPR 35</td></tr>
<tr><td>Electrical safety</td><td>Shock, fire and heat hazards</td><td>IEC 62368-1, IS 13252 (Part 1) in India</td></tr>
</tbody>
</table>

<h2>Which Approvals Use These Tests</h2>

<h3>WPC ETA (India)</h3>
<p>Products using de-licensed frequency bands, such as 2.4 GHz and parts of 5 GHz, need Equipment Type Approval from the WPC. Most go through the self-declaration route, which needs an <strong>RF test report from an accredited lab</strong>. See <a href="/resources/blogs/wpc-eta-documents-required-india-what-actually-need-before-filing">WPC ETA documents required</a>.</p>

<h3>TEC (India)</h3>
<p>Telecom products notified under the <strong>Mandatory Testing and Certification of Telecom Equipment (MTCTE)</strong> regime, such as routers, smartphones and IoT gateways, must be tested in <strong>TEC-designated labs</strong> to TEC's Essential Requirements, which cover EMC, safety, technical and sometimes security. See our <a href="/certification/india/tec-approval">TEC approval service</a>.</p>

<h3>BIS CRS (India)</h3>
<p>Many wireless products are also notified electronics under CRS, such as Bluetooth speakers, smart watches and power banks. They need safety testing in a BIS-recognised lab. See our <a href="/resources/blogs/bis-compulsory-registration-scheme-crs-explained">CRS guide</a>.</p>

<h3>FCC (USA)</h3>
<p>Transmitters need FCC certification, using RF, RF exposure and Part 15B tests from an FCC-recognised lab. See <a href="/resources/blogs/fcc-certification-cost-india-requirements">FCC certification cost and requirements</a>.</p>

<h3>CE and UKCA (Europe and UK)</h3>
<p>Radio products fall under the Radio Equipment Directive or the UK radio regulations. They need RF, EMC, safety and RF exposure assessment, usually through self-declaration.</p>

<h2>SAR Testing</h2>
<p><strong>Specific Absorption Rate (SAR)</strong> testing measures RF energy absorbed by the body. It applies to phones, tablets, smart watches, earbuds and other devices used close to the body.</p>
<ul>
<li><strong>India:</strong> 1.6 W/kg averaged over 1 gram of tissue for mobile handsets</li>
<li><strong>United States:</strong> 1.6 W/kg over 1 gram for head and body</li>
<li><strong>Europe:</strong> 2.0 W/kg over 10 grams for head and body</li>
</ul>
<p>Low-power devices may qualify for an exemption based on output power and distance, using a calculation instead of a full SAR test.</p>

<h2>How to Plan Wireless Testing</h2>
<ol>
<li><strong>List target markets and approvals.</strong> India only, or India plus the US and EU?</li>
<li><strong>List every radio,</strong> frequency band and power level.</li>
<li><strong>Check module certifications</strong> if you use pre-certified modules.</li>
<li><strong>Build one test plan</strong> that covers all markets, so one set of samples and one lab visit serve several approvals.</li>
<li><strong>Prepare samples:</strong> normal units plus units with test firmware that can set channels and power for RF testing.</li>
<li><strong>Run pre-compliance</strong> for EMC and RF where budget allows.</li>
</ol>

<h2>Cost and Timeline</h2>
<p>Cost depends on the number of radios, the number of markets, whether SAR is needed and how many models are covered. A single Bluetooth product for India only costs far less than a Wi-Fi plus LTE device for India, the US and the EU. Get a test plan and quote before sending samples.</p>
<p>For time, plan on <strong>two to four weeks</strong> for RF, EMC and safety on one product. SAR, TEC testing and retests after failures add time.</p>

<h2>Common Mistakes</h2>
<ul>
<li><strong>No test firmware.</strong> Labs cannot test RF without a way to fix channels and power.</li>
<li><strong>Testing for one market at a time.</strong> This often repeats the same tests.</li>
<li><strong>Assuming a certified module is enough.</strong> The final product still needs testing.</li>
<li><strong>Changing the antenna after testing.</strong> This can invalidate RF and SAR results.</li>
</ul>

<h2>How Instacertify Helps</h2>
<p>We build a single test plan across WPC, TEC, BIS, FCC and CE, coordinate testing in accredited and designated labs and file the approvals. See our <a href="/services/testing/wireless-testing">wireless testing services</a> and <a href="/certification/india/wpc-eta-approval">WPC ETA approval service</a>.</p>
HTML,
];
