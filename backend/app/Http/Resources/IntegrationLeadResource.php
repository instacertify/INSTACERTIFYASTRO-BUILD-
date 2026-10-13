<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An enquiry for outside tools reading it with an API key. IP address, browser and internal notes are left out.
 *
 * @mixin Lead
 */
class IntegrationLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'status' => $this->status,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'country' => $this->country,
            'manufacturer_type' => $this->manufacturer_type,
            'requirement' => $this->requirement,
            'service' => $this->service_path ? ['path' => $this->service_path, 'title' => $this->service?->title] : null,
            'product' => $this->product,
            'timeline' => $this->timeline,
            'message' => $this->message,
            'source_page' => $this->source_page,
            'referrer' => $this->referrer,
            'utm' => [
                'source' => $this->utm_source,
                'medium' => $this->utm_medium,
                'campaign' => $this->utm_campaign,
                'term' => $this->utm_term,
            ],
            'gclid' => $this->gclid,
            'marketing_opt_in' => $this->marketing_opt_in,
            'consented_at' => $this->consented_at?->toIso8601String(),
            'assigned_to' => $this->assignee?->name,
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'value' => $this->value,
            'lost_reason' => $this->lost_reason,
            'anonymised' => $this->anonymised_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
