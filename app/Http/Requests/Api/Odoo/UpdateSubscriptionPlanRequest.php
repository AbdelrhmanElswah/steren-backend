<?php

namespace App\Http\Requests\Api\Odoo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'sometimes|integer',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'currency_symbol' => 'nullable|string|max:10',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly,month,year',
            'features' => 'nullable|array',
            'max_listings' => 'nullable|integer|min:0',
            'max_photos' => 'nullable|integer|min:0',
            'subscriber_count' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_whatsapp_phone' => 'nullable|boolean',
            'is_badge' => 'nullable|boolean',
            'is_search' => 'nullable|boolean',
            'is_similar_cars' => 'nullable|boolean',
            'is_dedicated_account_rep' => 'nullable|boolean',
            'is_analytics' => 'nullable|boolean',
            'is_top_picks' => 'nullable|boolean',
            'is_priority_support' => 'nullable|boolean',
            'is_you_may_also_like' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}

