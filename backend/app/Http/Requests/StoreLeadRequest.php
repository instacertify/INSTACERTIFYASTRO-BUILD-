<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Tracking fields the visitor cannot see or fix: shortened to fit instead of rejecting the enquiry. */
    private const TRACKING_LIMITS = [
        'source_page' => 255, 'referrer' => 255, 'utm_source' => 100, 'utm_medium' => 100,
        'utm_campaign' => 150, 'utm_term' => 150, 'gclid' => 255,
    ];

    protected function prepareForValidation(): void
    {
        $tracking = [];
        foreach (self::TRACKING_LIMITS as $field => $limit) {
            if (is_string($value = $this->input($field))) {
                $tracking[$field] = Str::limit($value, $limit, '');
            }
        }

        // A form can still point at a service that was since renamed or removed; keep the enquiry, drop the link.
        $service = $this->input('service_path');
        $knownService = is_string($service) && Service::where('path', $service)->exists();

        $this->merge([
            ...$tracking,
            'service_path' => $knownService ? $service : null,
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
            'phone' => $this->filled('phone') ? preg_replace('/[^\d+]/', '', $this->input('phone')) : null,
            'type' => $this->input('type', 'quote'),
        ]);
    }

    public function rules(): array
    {
        return [
            'website' => ['prohibited'],
            'type' => ['required', Rule::in(array_keys(Lead::TYPES))],
            'name' => ['required_unless:type,newsletter', 'nullable', 'string', 'min:2', 'max:120'],
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:190'],
            'phone' => ['required_without:email', 'nullable', 'regex:/^\+?\d{8,15}$/'],
            'company' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'max:64'],
            'manufacturer_type' => ['nullable', Rule::in(['indian', 'foreign', 'importer', 'other'])],
            'requirement' => ['nullable', Rule::in(array_keys(Lead::REQUIREMENTS))],
            'service_path' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:190'],
            'timeline' => ['nullable', Rule::in(['urgent', '1-3-months', '3-6-months', 'exploring'])],
            'message' => ['nullable', 'string', 'max:3000'],
            'source_page' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'consent' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Please share an email or phone number so we can reach you.',
            'phone.required_without' => 'Please share a phone number or email so we can reach you.',
            'phone.regex' => 'Please enter a valid phone number with country code.',
            'requirement.in' => 'Please choose testing, certification, inspection or other.',
            'consent.accepted' => 'Please agree to be contacted about your enquiry.',
            'website.prohibited' => 'Unable to submit the form.',
        ];
    }
}
