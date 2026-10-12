<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Google icon per option card title, by landing page. Cards that already have an image or icon keep it. */
    private const ICONS = [
        'lp/emi-emc-testing' => ['TEC MTCTE & WPC' => 'cell_tower', 'Tenders & buyers' => 'handshake', 'Export – CE, FCC, UKCA' => 'public'],
        'lp/epr-registration' => ['Plastic packaging' => 'recycling', 'E-waste & battery' => 'battery_charging_full', 'Used oil & tyre' => 'oil_barrel'],
        'lp/food-packaging-migration-testing' => ['India – FSSAI' => 'restaurant', 'EU / UK export' => 'euro', 'US export' => 'flag'],
        'lp/g-mark-certification' => ['G-Mark' => 'workspace_premium', 'SABER (Saudi Arabia)' => 'app_registration', 'ECAS (UAE)' => 'verified'],
        'lp/imei-certificate-for-import' => ['IMEI / ICDR' => 'mobile', 'BIS CRS + WPC ETA' => 'settings_input_antenna', 'LMPC' => 'scale'],
        'lp/imei-certification' => ['ICDR registration' => 'mobile_check', 'TAC allocation' => 'pin', 'Both' => 'library_add_check'],
        'lp/imei-registration-for-manufacturers' => ['Own brand, own factory' => 'factory', 'Own brand, contract-made' => 'handshake', 'EMS / ODM factory' => 'precision_manufacturing'],
        'lp/ingress-protection-testing' => ['IP65 / IP66' => 'shower', 'IP67 / IP68' => 'pool', 'IP69K' => 'local_car_wash'],
        'lp/iso-certification' => ['ISO 9001' => 'workspace_premium', 'ISO 14001 & ISO 45001' => 'eco', 'ISO 22000, 13485, 27001' => 'category'],
        'lp/legal-metrology-registration' => ['Packaged commodities (LMPC)' => 'inventory_2', 'Weights & measures licences' => 'scale'],
        'lp/lmpc-certificate' => ['Importer' => 'directions_boat', 'Manufacturer' => 'factory', 'Packer' => 'package_2'],
        'lp/msds-authoring' => ['Export & shipping' => 'directions_boat', 'Amazon & marketplaces' => 'storefront', 'Tenders & buyers' => 'gavel'],
        'lp/msds-for-amazon' => ['New listing' => 'add_business', 'Listing blocked' => 'block', 'Fulfilment storage' => 'warehouse'],
        'lp/msds-for-export' => ['Buyer at destination' => 'handshake', 'Forwarder, airline or courier' => 'flight', 'Customs & port' => 'anchor'],
        'lp/msds-for-tender' => ['Government & PSU tenders' => 'account_balance', 'Institutional buyers' => 'domain', 'Vendor registration' => 'how_to_reg'],
        'lp/nutritional-value-testing' => ['India – FSSAI label' => 'nutrition', 'Export' => 'public', 'Claims & reformulation' => 'science'],
        'lp/saber-certification' => ['PCoC' => 'verified', 'SCoC' => 'local_shipping', 'Non-regulated goods' => 'inventory_2'],
        'lp/soncap-certification' => ['Product Certificate (PC)' => 'verified', 'SONCAP Certificate (SC)' => 'local_shipping', 'Inspection & testing' => 'science'],
    ];

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        foreach (LandingPage::query()->whereIn('path', array_keys(self::ICONS))->get() as $page) {
            $content = $page->content;
            $items = $content['options']['items'] ?? [];
            $changed = false;

            foreach ($items as $i => $item) {
                $icon = self::ICONS[$page->path][$item['title'] ?? ''] ?? null;
                if ($icon && blank($item['image'] ?? null) && blank($item['icon'] ?? null) && blank($item['iconImage'] ?? null)) {
                    $items[$i]['icon'] = $icon;
                    $changed = true;
                }
            }

            if ($changed) {
                $content['options']['items'] = $items;
                $page->content = $content;
                $page->save();
            }
        }
    }

    public function down(): void {}
};
