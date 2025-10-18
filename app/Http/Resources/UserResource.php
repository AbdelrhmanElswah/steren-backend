<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->formatted_phone,
            'birthday' => $this->birthday?->format('Y-m-d'),
            'profile_picture' => $this->profile_picture,
            'language' => $this->language,
            'theme' => $this->theme,
            'is_verified' => (bool) $this->is_verified,
            'dealer_application' => $this->when($this->relationLoaded('latestDealerApplication') && $this->latestDealerApplication, function () {
                $application = $this->latestDealerApplication;
                $status = $application->status?->name;
                if ($status === 'pending') {
                    return [
                        'status' => $status,
                        'status_badge_color' => $application->status_badge_color,
                        'message' => 'Your dealer application is pending review'
                    ];
                }
                return [
                    'id' => $application->id,
                    'seller_type' => $application->seller_type,
                    'dealership_company_name' => $application->dealership_company_name,
                    'status' => $status,
                    'status_badge_color' => $application->status_badge_color,
                    'payment_completed' => $application->payment_completed,
                    'created_at' => $application->created_at?->toISOString(),
                    'reviewed_at' => $application->reviewed_at?->toISOString(),
                    'approved_at' => $application->approved_at?->toISOString(),
                    'expires_at' => $application->expires_at?->toISOString(),
                    'rejection_reason' => $application->rejection_reason,
                    'days_until_expiration' => $application->days_until_expiration,
                    'subscription_plan' => $this->when($application->subscriptionPlan, [
                        'id' => $application->subscriptionPlan->id,
                        'name' => $application->subscriptionPlan->name,
                        'price' => $application->subscriptionPlan->price,
                        'formatted_price' => $application->subscriptionPlan->formatted_price,
                    ]),
                ];
            }),
        ];
    }
}


