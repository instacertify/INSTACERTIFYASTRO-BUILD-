<?php

use App\Models\Redirect;
use App\Models\Service;
use App\Models\Setting;
use App\Support\TestCategories;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Service::query()->where('kind', 'testing')->whereNull('group')->where('slug', 'like', 'battery-testing-standards-%')
            ->update(['group' => 'battery-testing']);

        Service::query()->where('kind', 'testing')->each(
            fn (Service $service) => $service->forceFill(['test_categories' => TestCategories::guess($service)])->saveQuietly()
        );

        // Icons chosen for a renamed industry follow it; icons of industries merged into another are dropped.
        $site = Setting::find('site');
        if ($icons = $site?->value['testCategories'] ?? null) {
            $renamed = ['it-telecom' => 'it-av-equipment', 'food-agri' => 'food-beverages', 'pharma-healthcare' => 'medical-healthcare', 'plastics-rubber' => 'plastics-polymers', 'toys-consumer' => 'toys-childcare'];
            foreach ($renamed as $from => $to) {
                if (isset($icons[$from]) && ! isset($icons[$to])) {
                    $icons[$to] = $icons[$from];
                }
            }
            $site->update(['value' => ['testCategories' => array_intersect_key($icons, TestCategories::ALL)] + $site->value]);
        }

        foreach (TestCategories::MOVED as $from => $to) {
            Redirect::firstOrCreate(
                ['from_path' => "/services/testing/{$from}"],
                ['to_path' => $to ? "/services/testing/{$to}" : '/services/testing', 'status_code' => 301, 'is_active' => true],
            );
        }
    }

    public function down(): void
    {
        Redirect::whereIn('from_path', collect(TestCategories::MOVED)->keys()->map(fn ($slug) => "/services/testing/{$slug}"))->delete();
    }
};
