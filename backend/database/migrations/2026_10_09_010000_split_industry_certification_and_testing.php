<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Testing services, product-testing industries and one-line descriptions for the two options on each industry page. */
    private const INDUSTRIES = [
        'electronic-industry' => [
            'testing' => ['services/testing/electronics-electricals-testing', 'services/testing/environmental-testing', 'services/testing/rohs-testing', 'services/testing/lm79'],
            'categories' => ['electrical-electronics', 'it-av-equipment', 'home-kitchen'],
            'certification' => 'BIS CRS registration, WPC and TEC approvals, BEE star labels and EPR for e-waste, filed and followed up for you.',
            'testingText' => 'Safety, EMC, RoHS, climatic and lighting tests at NABL-accredited and BIS-recognised labs, with reports ready for BIS.',
        ],
        'toys-industry' => [
            'testing' => ['services/testing/toys', 'services/testing/reach-testing'],
            'categories' => ['toys-childcare'],
            'certification' => 'The BIS ISI mark that toys need to be sold in India, plus CE marking and G-Mark for export.',
            'testingText' => 'Mechanical, chemical and flammability tests to IS 9873 and the EN 71 and ASTM F963 standards.',
        ],
        'polymers-plastics-industry' => [
            'testing' => ['services/testing/plastics-polymers-testing', 'services/testing/reach-testing', 'services/testing/rohs-testing'],
            'categories' => ['plastics-polymers', 'rubber-tyres'],
            'certification' => 'BIS ISI marks for plastic products and EPR registration for plastic packaging waste.',
            'testingText' => 'Mechanical, thermal and restricted-substance (REACH and RoHS) tests for plastics, polymers and rubber.',
        ],
        'cosmetics-industry' => [
            'testing' => ['services/testing/cosmetics-testing'],
            'categories' => ['cosmetics-personal-care'],
            'certification' => 'CDSCO cosmetics registration for imports, SFDA for Saudi Arabia, Halal certification and safety data sheets.',
            'testingText' => 'Microbial, stability, heavy-metal and skin-safety tests for cosmetics and personal care products.',
        ],
        'food-industry' => [
            'testing' => ['services/testing/food-beverages-testing'],
            'categories' => ['food-beverages', 'agriculture-fertilizers'],
            'certification' => 'FSSAI central and import licences, HACCP and Halal certification for food businesses.',
            'testingText' => 'Nutritional, microbial, pesticide-residue and shelf-life tests to FSSAI norms.',
        ],
        'software-industry' => [
            'testing' => ['services/testing/wireless-testing', 'cispr-32', 'is-iec-62368-1'],
            'categories' => ['it-av-equipment'],
            'certification' => 'TEC and STQC approvals, EU cybersecurity compliance, IMEI registration and ISO certification.',
            'testingText' => 'Wireless and RF, EMC and safety tests for phones, IoT and connected devices, including 4G and 5G conformance.',
        ],
    ];

    public function up(): void
    {
        Schema::table('industries', function (Blueprint $table) {
            $table->text('certification_text')->nullable()->after('service_paths');
            $table->json('testing_paths')->nullable()->after('certification_text');
            $table->json('test_categories')->nullable()->after('testing_paths');
            $table->text('testing_text')->nullable()->after('test_categories');
        });

        $testing = DB::table('services')->where('kind', 'testing')->pluck('path')->all();
        $published = DB::table('services')->where('is_published', true)->pluck('path')->all();

        foreach (DB::table('industries')->get() as $industry) {
            $paths = json_decode($industry->service_paths ?? '[]', true) ?: [];
            $preset = self::INDUSTRIES[$industry->slug] ?? null;
            $tests = array_values(array_intersect($paths, $testing));
            if ($preset) {
                $tests = array_values(array_unique([...$tests, ...array_intersect($preset['testing'], $published)]));
            }

            DB::table('industries')->where('id', $industry->id)->update([
                'service_paths' => json_encode(array_values(array_diff($paths, $testing))),
                'testing_paths' => json_encode($tests),
                'test_categories' => json_encode($preset['categories'] ?? []),
                'certification_text' => $preset['certification'] ?? null,
                'testing_text' => $preset['testingText'] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('industries')->get() as $industry) {
            $paths = [...(json_decode($industry->service_paths ?? '[]', true) ?: []), ...(json_decode($industry->testing_paths ?? '[]', true) ?: [])];
            DB::table('industries')->where('id', $industry->id)->update(['service_paths' => json_encode(array_values(array_unique($paths)))]);
        }

        Schema::table('industries', function (Blueprint $table) {
            $table->dropColumn(['certification_text', 'testing_paths', 'test_categories', 'testing_text']);
        });
    }
};
