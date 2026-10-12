<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Old and new meta descriptions; a description an editor has since changed is left alone. */
    private const DESCRIPTIONS = [
        'lp/saber-certification' => [
            'SABER certification for exporters to Saudi Arabia: PCoC per product and SCoC per shipment, worked through your importer’s account, back from your sailing date.',
            'SABER certification for exporters to Saudi Arabia: PCoC per product, SCoC per shipment, via your importer’s account, planned back from your sailing date.',
        ],
        'lp/epr-registration' => [
            'EPR registration for plastic packaging, e-waste, battery, used oil and tyres. Categories mapped, yearly cost modelled before filing, annual returns handled.',
            'EPR registration for plastic packaging, e-waste, batteries, used oil and tyres. Categories mapped, yearly cost modelled before filing, returns handled.',
        ],
        'lp/soncap-certification' => [
            'SONCAP for exporters to Nigeria: Product Certificate for regulated goods and a SONCAP Certificate per shipment, planned around your Form M and sailing date.',
            'SONCAP for exporters to Nigeria: Product Certificate for regulated goods and a SONCAP Certificate per shipment, planned around Form M and sailing date.',
        ],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        foreach (self::DESCRIPTIONS as $path => $texts) {
            $page = LandingPage::where('path', $path)->first();
            if ($page && ($page->seo['description'] ?? null) === $texts[$from]) {
                $page->update(['seo' => [...$page->seo, 'description' => $texts[$to]]]);
            }
        }
    }
};
