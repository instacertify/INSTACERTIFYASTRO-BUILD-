<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Jobs\ProcessNewLead;
use App\Jobs\SendServerConversion;
use App\Jobs\SyncLeadToWebhook;
use App\Models\Lead;
use App\Support\ConversionApis;
use App\Support\Privacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create([
            ...Arr::except($request->validated(), ['website']),
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'consent_version' => Privacy::version(),
            'consented_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        // The lead is saved; on a synchronous queue a mail or CRM outage must not show the visitor an error.
        rescue(function () use ($lead) {
            ProcessNewLead::dispatch($lead);
        });
        if (config('instacertify.leads.webhook_url')) {
            rescue(function () use ($lead) {
                SyncLeadToWebhook::dispatch($lead);
            });
        }
        $context = $request->conversionContext();
        foreach (ConversionApis::platformsFor($context) as $platform) {
            rescue(function () use ($lead, $platform, $context) {
                SendServerConversion::dispatch($lead, $platform, $context);
            });
        }

        return response()->json([
            'data' => [
                'id' => $lead->uuid,
                'message' => 'Thanks! A certification specialist will contact you within 1 business day.',
            ],
        ], 201);
    }
}
