<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class GetSubscriptionPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'id' => 'nullable|integer|exists:subscription_plans,id',
            'odoo_id' => 'nullable|string|exists:subscription_plans,odoo_id',
            'is_active' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'billing_cycle' => 'nullable|string|in:monthly,yearly,year',
        ];
    }
}

