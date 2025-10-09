<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'billing_cycle' => $this->billing_cycle,
            'features' => $this->features,
            'max_listings' => $this->max_listings,
            'max_photos' => $this->max_photos,
            'subscriber_count' => $this->subscriber_count,
            'is_active' => $this->is_active,
            'is_popular' => $this->is_popular,
            'is_featured' => $this->is_featured,
            'is_whatsapp_phone' => $this->is_whatsapp_phone,
            'is_badge' => $this->is_badge,
            'is_search' => $this->is_search,
            'is_similar_cars' => $this->is_similar_cars,
            'is_dedicated_account_rep' => $this->is_dedicated_account_rep,
            'is_analytics' => $this->is_analytics,
            'is_top_picks' => $this->is_top_picks,
            'is_priority_support' => $this->is_priority_support,
            'is_you_may_also_like' => $this->is_you_may_also_like,
            'sort_order' => $this->sort_order,
            'formatted_price' => $this->formatted_price,
            'price_per_month' => (float) $this->price_per_month,
            'formatted_price_per_month' => $this->formatted_price_per_month,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

