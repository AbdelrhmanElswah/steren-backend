<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
            'payment_details' => 'nullable|array',
            'payment_details.payment_method' => 'nullable|string|in:credit_card,debit_card,paypal,stripe,wallet',
            'payment_details.transaction_id' => 'nullable|string|max:255',
            'payment_details.payment_gateway' => 'nullable|string|max:100',
            'payment_details.amount' => 'nullable|numeric|min:0',
            'payment_details.currency' => 'nullable|string|size:3',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subscription_plan_id.required' => 'Please select a subscription plan',
            'subscription_plan_id.exists' => 'Selected subscription plan does not exist',
            'payment_details.payment_method.in' => 'Invalid payment method selected',
            'payment_details.currency.size' => 'Currency code must be exactly 3 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'subscription_plan_id' => 'subscription plan',
            'payment_details.payment_method' => 'payment method',
            'payment_details.transaction_id' => 'transaction ID',
            'payment_details.amount' => 'payment amount',
        ];
    }
}

