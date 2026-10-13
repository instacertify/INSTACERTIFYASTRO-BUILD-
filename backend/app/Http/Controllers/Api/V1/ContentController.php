<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndustryResource;
use App\Http\Resources\ServiceResource;
use App\Models\Industry;
use App\Models\Office;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Support\Careers;
use App\Support\ContactBoxes;
use App\Support\ConversionApis;
use App\Support\FooterMenu;
use App\Support\HeaderMenu;
use App\Support\HomePage;
use App\Support\Images;
use App\Support\Privacy;
use App\Support\TestCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class ContentController extends Controller
{
    public function site(): JsonResponse
    {
        $site = Arr::except(Setting::get('site', []), ['hero', 'stats', 'whyUs', 'clientLogos', 'clientLogosEnabled', 'clientLogosTitle', 'testCategories']);
        $site['favicon'] = Images::url($site['favicon'] ?? null);
        $site['logo'] = Images::url($site['logo'] ?? null);
        if (isset($site['popup'])) {
            $site['popup']['image'] = Images::url($site['popup']['image'] ?? null);
        }
        $site['nav'] = HeaderMenu::forApi($site['nav'] ?? []);
        $site['footer'] = FooterMenu::forApi($site['footer'] ?? []);
        $site['home'] = HomePage::forApi();
        $site['process'] = HomePage::process($site['home']);
        $site['privacy'] = Arr::only(Privacy::settings(), ['version', 'grievanceOfficer', 'grievanceEmail', 'applicationMonths', 'consentLogMonths', 'ipDays']);
        $site['careers'] = Careers::settings();
        $site['contactBoxes'] = ContactBoxes::settings();
        $site['integrations'] = [...($site['integrations'] ?? []), 'serverSide' => ConversionApis::publicFlags()];
        $site['offices'] = Office::published()->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Office $office) => [
                'name' => $office->name,
                'type' => $office->type,
                'typeLabel' => Office::TYPES[$office->type] ?? null,
                'address' => $office->fullAddress(),
                'street' => $office->street,
                'city' => $office->city,
                'region' => $office->region,
                'postalCode' => $office->postal_code,
                'country' => $office->country,
                'landmark' => $office->landmark,
                'phone' => $office->phone,
                'email' => $office->email,
                'contactPerson' => $office->contact_person,
                'hours' => $office->hours,
                'mapEmbed' => $office->mapEmbed(),
                'mapLink' => $office->mapLink(),
            ])
            ->all();

        return response()->json([
            'data' => [
                ...$site,
                'taxonomy' => [
                    'kinds' => Service::KINDS,
                    'regions' => Service::REGIONS,
                    'groups' => Service::GROUPS,
                    'testCategories' => TestCategories::forApi(),
                    'testCategoryIconAlign' => Setting::get('site', [])['testCategories']['iconAlign'] ?? null,
                ],
            ],
        ]);
    }

    public function services(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'kind' => ['nullable', 'string', 'in:'.implode(',', array_keys(Service::KINDS))],
            'region' => ['nullable', 'string', 'in:'.implode(',', array_keys(Service::REGIONS))],
            'group' => ['nullable', 'string', 'in:'.implode(',', array_keys(Service::GROUPS))],
            'featured' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $services = Service::published()
            ->when($validated['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($validated['region'] ?? null, fn ($q, $v) => $q->where('region', $v))
            ->when($validated['group'] ?? null, fn ($q, $v) => $q->where('group', $v))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($validated['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('title', "%{$term}%")
                ->orWhereLike('summary', "%{$term}%")))
            ->orderBy('sort')
            ->get();

        return ServiceResource::collection($services);
    }

    public function service(string $path): ServiceResource
    {
        return new ServiceResource(
            Service::published()->where('path', trim($path, '/'))->firstOrFail()
        );
    }

    public function industries(): AnonymousResourceCollection
    {
        return IndustryResource::collection(Industry::published()->orderBy('sort')->get());
    }

    public function industry(string $slug): IndustryResource
    {
        return new IndustryResource(Industry::published()->where('slug', $slug)->firstOrFail());
    }

    public function testimonials(): JsonResponse
    {
        return response()->json([
            'data' => Testimonial::published()->orderBy('sort')->latest('id')->get()
                ->map(fn (Testimonial $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'context' => $t->context,
                    'rating' => $t->rating,
                    'quote' => $t->quote,
                    'photo' => Images::url($t->photo),
                    'featured' => $t->is_featured,
                ]),
        ]);
    }
}
