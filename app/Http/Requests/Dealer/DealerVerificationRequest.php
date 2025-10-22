<?php

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerVerificationRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'partner_id' => 'required|integer',
            'seller_type' => 'required|string|in:authorized,independent',
            'dealership_name' => 'required|string|max:255',
            'owner_manager_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email_address' => 'required|email|max:255',
            'city_address' => 'required|string|max:500',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'automotive_brand' => 'nullable|string|max:255',
            'business_license_name' => 'nullable|string|max:255',
            'vehicles_in_stock' => 'nullable|numeric|min:0|max:9999',
            'years_in_business' => 'nullable|numeric|min:0|max:100',
            'authorization_letter' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'business_license' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'partner_id.required' => 'Partner ID is required.',
            'partner_id.integer' => 'Partner ID must be a valid integer.',
            'seller_type.required' => 'Please select your seller type.',
            'seller_type.in' => 'Invalid seller type selected.',
            'dealership_name.required' => 'Dealership name is required.',
            'owner_manager_name.required' => 'Owner/Manager full name is required.',
            'phone_number.required' => 'Phone number is required.',
            'email_address.required' => 'Email address is required.',
            'email_address.email' => 'Please provide a valid email address.',
            'city_address.required' => 'City & Address is required.',
            'subscription_plan_id.required' => 'Please select a subscription plan.',
            'subscription_plan_id.exists' => 'Selected subscription plan is invalid.',
            'automotive_brand.required' => 'Automotive brand is required for authorized sellers.',
            'authorization_letter.required' => 'Authorization letter file is required for authorized sellers.',
            'authorization_letter.file' => 'Authorization letter must be a valid file.',
            'authorization_letter.mimes' => 'Authorization letter must be a PDF, JPG, JPEG, or PNG file.',
            'authorization_letter.max' => 'Authorization letter must not exceed 10MB.',
            'business_license.required' => 'Business license file is required for authorized sellers.',
            'business_license.file' => 'Business license must be a valid file.',
            'business_license.mimes' => 'Business license must be a PDF, JPG, JPEG, or PNG file.',
            'business_license.max' => 'Business license must not exceed 10MB.',
            'business_license_name.required' => 'Business license name is required for authorized sellers.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'partner_id' => 'partner ID',
            'seller_type' => 'seller type',
            'dealership_name' => 'dealership name',
            'owner_manager_name' => 'owner/manager name',
            'vehicles_in_stock' => 'number of vehicles in stock',
            'years_in_business' => 'years in business',
            'phone_number' => 'phone number',
            'email_address' => 'email address',
            'city_address' => 'city & address',
            'subscription_plan_id' => 'subscription plan',
            'automotive_brand' => 'automotive brand',
            'authorization_letter' => 'authorization letter file',
            'business_license' => 'business license file',
            'business_license_name' => 'business license name',
        ];
    }
}
