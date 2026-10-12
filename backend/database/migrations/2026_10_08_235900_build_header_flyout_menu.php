<?php

use App\Models\Industry;
use App\Models\Service;
use App\Models\Setting;
use App\Support\TestCategories;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::where('key', 'site')->first();
        if (! $setting) {
            return;
        }
        $site = $setting->value ?? [];

        $services = Service::where('is_published', true)->orderBy('sort')->orderBy('title')->get(['path', 'title']);
        $under = fn (string $prefix) => $services
            ->filter(fn (Service $s) => str_starts_with($s->path, $prefix.'/') && substr_count($s->path, '/') === substr_count($prefix, '/') + 1)
            ->map(fn (Service $s) => ['label' => $s->title, 'href' => '/'.$s->path])
            ->values()->all();
        $link = fn (string $label, string $href, array $children = [], ?string $description = null) => array_filter(
            ['label' => $label, 'href' => $href, 'description' => $description, 'children' => $children]
        );

        $regions = [
            ['India', 'certification/india', 'BIS, CRS, WPC, TEC, BEE, CDSCO'],
            ['Europe & Middle East', 'certification/emea', 'CE, UKCA, SABER, SFDA'],
            ['Asia', 'certification/asia', 'CCC, PSE, MIC, SNI'],
            ['Americas', 'certification/americas', 'FCC, NOM, Anatel'],
            ['Global', 'certification/global-certifications', 'ISO, CB Scheme, HACCP, Halal'],
        ];
        $groups = [
            'environment-compliance' => 'EPR & environment',
            'import-export' => 'Import & export',
            'business-licenses' => 'Business licences',
            'man-power' => 'Manpower & labour',
            'factories-and-building' => 'Factories & building',
            'others' => 'Other solutions',
        ];
        $registrations = 'services/indian-registration-compliances';

        $site['nav'] = [
            $link('Certifications', '/certification', array_map(
                fn (array $r) => $link($r[0], '/'.$r[1], $under($r[1]), $r[2]),
                $regions,
            )),
            $link('Services', '/services', [
                $link('Product testing', '/services/testing', collect(TestCategories::options())
                    ->map(fn (string $label, string $slug) => ['label' => $label, 'href' => '/services/testing/'.$slug])
                    ->values()->all(), 'Accredited labs, 20 industries'),
                $link('Indian registrations & licences', '/'.$registrations, collect($groups)
                    ->filter(fn (string $label, string $key) => $under($registrations.'/'.$key))
                    ->map(fn (string $label, string $key) => ['label' => $label, 'href' => "/{$registrations}/{$key}"])
                    ->values()->all(), 'EPR, IEC, FSSAI, LMPC'),
                $link('EPR & environment', "/{$registrations}/environment-compliance", $under("{$registrations}/environment-compliance")),
                $link('Quality assurance', '/services/qualitiy', $under('services/qualitiy'), 'Inspection & market surveillance'),
            ]),
            $link('Industries', '/industries', Industry::orderBy('sort')->get(['slug', 'name'])
                ->map(fn (Industry $i) => ['label' => $i->name, 'href' => '/industries/'.$i->slug])->all()),
            $link('Resources', '/resources', [
                $link('Blog', '/resources/blogs'),
                $link('Regulatory news', '/resources/news'),
                $link('Regulatory updates', '/resources/update'),
                $link('Audits & inspections', '/resources/audits'),
                $link('Events', '/resources/events'),
                $link('Standards library', '/resources/standards'),
            ]),
            $link('Contact', '/contact'),
        ];

        $setting->value = $site;
        $setting->save();
    }

    public function down(): void
    {
        //
    }
};
