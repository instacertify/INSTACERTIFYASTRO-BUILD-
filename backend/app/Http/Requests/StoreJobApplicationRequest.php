<?php

namespace App\Http\Requests;

use App\Models\JobApplication;
use App\Models\Vacancy;
use App\Support\Careers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobApplicationRequest extends FormRequest
{
    public ?Vacancy $vacancy = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
            'phone' => $this->filled('phone') ? preg_replace('/[^\d+]/', '', $this->input('phone')) : null,
            'consent' => $this->boolean('consent'),
        ]);
    }

    public function rules(): array
    {
        return [
            'website' => ['prohibited'],
            'vacancy' => ['nullable', 'string', 'max:190'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'regex:/^\+?\d{8,15}$/'],
            'city' => ['nullable', 'string', 'max:80'],
            'linkedin_url' => ['nullable', 'url:https', 'max:255'],
            'experience_years' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'current_ctc' => ['nullable', 'string', 'max:40'],
            'expected_ctc' => ['nullable', 'string', 'max:40'],
            'notice_period' => ['nullable', Rule::in(array_keys(JobApplication::NOTICE_PERIODS))],
            'cover_letter' => ['nullable', 'string', 'max:3000'],
            'cv' => ['required', 'file', 'extensions:pdf,doc,docx', 'mimes:pdf,doc,docx', 'max:'.config('instacertify.careers.cv_max_kb')],
            'source_page' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'consent' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $slug = $this->input('vacancy');
            if (blank($slug)) {
                if (! Careers::settings()['generalApplications']) {
                    $validator->errors()->add('vacancy', 'Please apply for one of the open positions.');
                }

                return;
            }

            $this->vacancy = Vacancy::where('slug', $slug)->first();
            if (! $this->vacancy?->isOpen()) {
                $validator->errors()->add('vacancy', 'This position is no longer accepting applications. Please see our other openings.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid phone number with country code.',
            'cv.required' => 'Please attach your CV.',
            'cv.extensions' => 'Please upload your CV as a PDF or Word document.',
            'cv.mimes' => 'Please upload your CV as a PDF or Word document.',
            'cv.max' => 'Your CV must be smaller than '.round(config('instacertify.careers.cv_max_kb') / 1024).' MB.',
            'linkedin_url.url' => 'Please enter the full https:// link to your profile.',
            'consent.accepted' => 'Please agree so we can process your application.',
            'website.prohibited' => 'Unable to submit the form.',
        ];
    }
}
