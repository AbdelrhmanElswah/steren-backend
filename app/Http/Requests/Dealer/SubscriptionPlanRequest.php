<?php

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPlanRequest extends FormRequest
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
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|string|max:50',
            'payment_data' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subscription_plan_id.required' => 'Please select a subscription plan.',
            'subscription_plan_id.exists' => 'Selected subscription plan is invalid.',
            'payment_method.required' => 'Payment method is required.',
            'payment_data.array' => 'Payment data must be valid.',
        ];
    }
}
