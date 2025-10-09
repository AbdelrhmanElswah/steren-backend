<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->user->partner_id,
            'seller_type' => $this->seller_type,
            'dealership_name' => $this->dealership_company_name,
            'owner_manager_name' => $this->owner_manager_name,
            'vehicles_in_stock' => $this->vehicles_in_stock,
            'years_in_business' => $this->years_in_business,
            'phone_number' => $this->phone_number,
            'email_address' => $this->email,
            'city_address' => $this->city_address,
            'subscription_plan' => [
                'id' => $this->subscriptionPlan->id,
                'name' => $this->subscriptionPlan->name,
                'price' => $this->subscriptionPlan->price,
                'billing_cycle' => $this->subscriptionPlan->billing_cycle,
            ],
            'status' => $this->status,
            'status_badge_color' => $this->status_badge_color,
            'payment_completed' => $this->payment_completed,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'approved_at' => $this->approved_at,
            'expires_at' => $this->expires_at,
            'rejection_reason' => $this->rejection_reason,
            'days_until_expiration' => $this->days_until_expiration,
            'is_active' => $this->isActive(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'is_expired' => $this->isExpired(),
            
            // Authorized seller specific fields
            'automotive_brand' => $this->when($this->seller_type === 'authorized', $this->automotive_brand_represented),
            'authorization_letter_url' => $this->when(
                $this->seller_type === 'authorized' && $this->authorization_letter_path,
                \Storage::disk('public')->url($this->authorization_letter_path)
            ),
            'business_license_number' => $this->when($this->seller_type === 'authorized', $this->business_license_number),
            
            // Reviewer information
            'reviewer' => $this->when($this->reviewer, [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->full_name,
            ]),
        ];
    }
}
