<?php

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Account & Role Information
            'seller_type' => ['required', 'string', Rule::in(['authorized', 'independent'])],
            'owner_manager_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'email_address' => 'required|email|max:255',
            
            // Business Details (required for both types)
            'dealership_company_name' => 'required|string|max:255',
            'dealership_name' => 'required|string|max:255',
            'vehicles_in_stock' => 'nullable|integer|min:0|max:9999',
            'years_in_business' => 'nullable|integer|min:0|max:100',
            'city_address' => 'required|string|max:500',
            
            // Additional fields for Authorized Sellers only
            'automotive_brand_represented' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($this->input('seller_type') === 'authorized')
            ],
            'authorization_letter' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240', // 10MB
                Rule::requiredIf($this->input('seller_type') === 'authorized')
            ],
            'business_license_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($this->input('seller_type') === 'authorized')
            ],
            
            // Subscription Plan Selection
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            
            // Terms and Conditions
            'terms_accepted' => 'required|boolean|accepted',
            'information_confirmed' => 'required|boolean|accepted',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'seller_type.required' => 'Please select your seller type.',
            'seller_type.in' => 'Invalid seller type selected.',
            'owner_manager_name.required' => 'Owner/Manager full name is required.',
            'phone_number.required' => 'Phone number is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'dealership_company_name.required' => 'Dealership/Company name is required.',
            'city_address.required' => 'City & Address is required.',
            'automotive_brand_represented.required' => 'Automotive brand represented is required for authorized sellers.',
            'authorization_letter.required' => 'Authorization letter/dealership certificate is required for authorized sellers.',
            'authorization_letter.file' => 'Authorization letter must be a file.',
            'authorization_letter.mimes' => 'Authorization letter must be a PDF or image file.',
            'authorization_letter.max' => 'Authorization letter file size cannot exceed 10MB.',
            'business_license_number.required' => 'Business license number is required for authorized sellers.',
            'subscription_plan_id.required' => 'Please select a subscription plan.',
            'subscription_plan_id.exists' => 'Selected subscription plan is invalid.',
            'terms_accepted.required' => 'You must accept the terms and conditions.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions.',
            'information_confirmed.required' => 'You must confirm that all information is correct.',
            'information_confirmed.accepted' => 'You must confirm that all information is correct.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'seller_type' => 'seller type',
            'owner_manager_name' => 'owner/manager name',
            'phone_number' => 'phone number',
            'dealership_company_name' => 'dealership/company name',
            'vehicles_in_stock' => 'number of vehicles in stock',
            'years_in_business' => 'years in business',
            'city_address' => 'city & address',
            'automotive_brand_represented' => 'automotive brand represented',
            'authorization_letter' => 'authorization letter',
            'business_license_number' => 'business license number',
            'subscription_plan_id' => 'subscription plan',
            'terms_accepted' => 'terms and conditions',
            'information_confirmed' => 'information confirmation',
        ];
    }
}
