<?php

namespace App\Http\Requests;

use App\Support\PaymentSettings;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        $this->merge([
            // "1,50,000.00" or "₹ 1500" as typed from an invoice.
            'amount' => is_string($amount) ? preg_replace('/[^\d.]/', '', $amount) : $amount,
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
            'phone' => $this->filled('phone') ? preg_replace('/[^\d+]/', '', $this->input('phone')) : null,
            'invoice_number' => $this->filled('invoice_number') ? strtoupper(trim($this->input('invoice_number'))) : null,
        ]);
    }

    public function rules(): array
    {
        $settings = PaymentSettings::settings();

        return [
            'website' => ['prohibited'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'regex:/^\+?\d{8,15}$/'],
            'company' => ['nullable', 'string', 'max:160'],
            'invoice_number' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9\/\-_. ]+$/'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:'.$settings['minAmount'], 'max:'.$settings['maxAmount']],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        $settings = PaymentSettings::settings();
        $currency = $settings['currency'];

        return [
            'invoice_number.required' => 'Enter the invoice number shown on your invoice.',
            'phone.regex' => 'Please enter a valid phone number with country code.',
            'invoice_number.regex' => 'Use only the letters, numbers and - / . shown on your invoice.',
            'amount.decimal' => 'Enter the amount with at most two decimal places.',
            'amount.min' => "The minimum online payment is {$currency} ".number_format($settings['minAmount']).'.',
            'amount.max' => "Online payments are limited to {$currency} ".number_format($settings['maxAmount']).'. Please contact us for bank transfer details.',
            'website.prohibited' => 'Unable to submit the form.',
        ];
    }
}
