<?php

namespace App\Services;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\CarListing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Check if user has an active subscription
     * Validates in real-time based on expires_at date
     */
    public function hasActiveSubscription(User $user): bool
    {
        $subscription = $user->activeSubscription;
        
        if (!$subscription) {
            return false;
        }

        // Real-time validation based on expires_at date
        if ($subscription->expires_at && $subscription->expires_at->isPast()) {
            return false;
        }

        return $subscription->status === 'active';
    }

    /**
     * Get user's active subscription
     */
    public function getActiveSubscription(User $user): ?UserSubscription
    {
        return $user->activeSubscription;
    }

    /**
     * Get user's subscription plan
     */
    public function getSubscriptionPlan(User $user): ?SubscriptionPlan
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return null;
        }

        return $subscription->subscriptionPlan;
    }

    /**
     * Check if user can create a new listing based on their subscription
     * Free users (no subscription) can create 1 listing per month
     */
    public function canCreateListing(User $user): array
    {
        // Check if user has active subscription
        if (!$this->hasActiveSubscription($user)) {
            // Free tier: 1 listing per month
            return $this->checkFreeTierLimit($user);
        }

        $plan = $this->getSubscriptionPlan($user);
        
        if (!$plan) {
            return [
                'can_create' => false,
                'reason' => 'Subscription plan not found.',
                'code' => 'PLAN_NOT_FOUND'
            ];
        }

        // Count ALL user's listings including soft-deleted ones
        // Deleted listings still count toward the limit
        $currentListingsCount = CarListing::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'rejected'])
            ->count(); // Includes soft-deleted

        // Check if user has reached their listing limit
        if ($plan->max_listings !== null && $currentListingsCount >= $plan->max_listings) {
            return [
                'can_create' => false,
                'reason' => "You have reached your listing limit of {$plan->max_listings} listings. Please upgrade your plan.",
                'code' => 'LISTING_LIMIT_REACHED',
                'current_listings' => $currentListingsCount,
                'max_listings' => $plan->max_listings
            ];
        }

        return [
            'can_create' => true,
            'current_listings' => $currentListingsCount,
            'max_listings' => $plan->max_listings,
            'remaining_listings' => $plan->max_listings ? ($plan->max_listings - $currentListingsCount) : null,
            'plan_name' => $plan->name
        ];
    }

    /**
     * Check free tier limit (1 listing per month)
     */
    private function checkFreeTierLimit(User $user): array
    {
        // Count listings created this month (including deleted)
        $currentMonth = Carbon::now()->startOfMonth();
        $listingsThisMonth = CarListing::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth)
            ->count();

        if ($listingsThisMonth >= 1) {
            return [
                'can_create' => false,
                'reason' => 'Free users can create 1 listing per month. Please subscribe to a plan for more listings.',
                'code' => 'FREE_TIER_LIMIT_REACHED',
                'current_listings' => $listingsThisMonth,
                'max_listings' => 1,
                'plan_name' => 'Free'
            ];
        }

        return [
            'can_create' => true,
            'current_listings' => $listingsThisMonth,
            'max_listings' => 1,
            'remaining_listings' => 1 - $listingsThisMonth,
            'plan_name' => 'Free'
        ];
    }

    /**
     * Get subscription limits for a user
     * Includes free tier limits if no subscription
     */
    public function getSubscriptionLimits(User $user): array
    {
        // Free tier
        if (!$this->hasActiveSubscription($user)) {
            $currentMonth = Carbon::now()->startOfMonth();
            $listingsThisMonth = CarListing::where('user_id', $user->id)
                ->where('created_at', '>=', $currentMonth)
                ->count();
            
            return [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'max_listings' => 1,
                'max_listings_period' => 'monthly',
                'current_listings' => $listingsThisMonth,
                'remaining_listings' => max(0, 1 - $listingsThisMonth),
                'max_photos' => 5,
                'features' => []
            ];
        }

        $plan = $this->getSubscriptionPlan($user);
        
        if (!$plan) {
            return [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'max_listings' => 1,
                'max_listings_period' => 'monthly',
                'current_listings' => 0,
                'remaining_listings' => 1,
                'max_photos' => 5,
                'features' => []
            ];
        }

        // Count ALL listings (including soft-deleted)
        $currentListingsCount = CarListing::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'rejected'])
            ->count();

        return [
            'has_subscription' => true,
            'plan_name' => $plan->name,
            'max_listings' => $plan->max_listings,
            'max_listings_period' => 'total',
            'current_listings' => $currentListingsCount,
            'remaining_listings' => $plan->max_listings ? max(0, $plan->max_listings - $currentListingsCount) : null,
            'max_photos' => $plan->max_photos,
            'features' => [
                'whatsapp_phone' => $plan->is_whatsapp_phone,
                'badge' => $plan->is_badge,
                'search' => $plan->is_search,
                'similar_cars' => $plan->is_similar_cars,
                'dedicated_account_rep' => $plan->is_dedicated_account_rep,
                'analytics' => $plan->is_analytics,
                'top_picks' => $plan->is_top_picks,
                'priority_support' => $plan->is_priority_support,
                'you_may_also_like' => $plan->is_you_may_also_like,
            ]
        ];
    }

    /**
     * Check if user can upload given number of photos
     * Free users have a default limit of 5 photos
     */
    public function canUploadPhotos(User $user, int $photoCount): array
    {
        // Free tier: 5 photos max
        if (!$this->hasActiveSubscription($user)) {
            $freeMaxPhotos = 5;
            
            if ($photoCount > $freeMaxPhotos) {
                return [
                    'can_upload' => false,
                    'reason' => "Free users can upload a maximum of {$freeMaxPhotos} photos per listing. You attempted to upload {$photoCount} photos. Please subscribe to a plan for more photos.",
                    'code' => 'PHOTO_LIMIT_EXCEEDED',
                    'max_photos' => $freeMaxPhotos,
                    'attempted_photos' => $photoCount
                ];
            }
            
            return [
                'can_upload' => true,
                'max_photos' => $freeMaxPhotos,
                'uploaded_photos' => $photoCount
            ];
        }

        $plan = $this->getSubscriptionPlan($user);
        
        if (!$plan) {
            return [
                'can_upload' => false,
                'reason' => 'Subscription plan not found.',
                'code' => 'PLAN_NOT_FOUND',
                'max_photos' => 0
            ];
        }

        // Check if photo count exceeds plan limit
        if ($plan->max_photos !== null && $photoCount > $plan->max_photos) {
            return [
                'can_upload' => false,
                'reason' => "You can upload a maximum of {$plan->max_photos} photos per listing with your current plan. You attempted to upload {$photoCount} photos.",
                'code' => 'PHOTO_LIMIT_EXCEEDED',
                'max_photos' => $plan->max_photos,
                'attempted_photos' => $photoCount
            ];
        }

        return [
            'can_upload' => true,
            'max_photos' => $plan->max_photos,
            'uploaded_photos' => $photoCount
        ];
    }

    /**
     * Subscribe user to a plan
     */
    public function subscribe(User $user, int $subscriptionPlanId, ?array $paymentDetails = null): UserSubscription
    {
        $plan = SubscriptionPlan::findOrFail($subscriptionPlanId);

        // Cancel any existing active subscription
        $existingSubscription = $this->getActiveSubscription($user);
        if ($existingSubscription) {
            $existingSubscription->cancel();
        }

        // Calculate expiry date based on billing cycle
        $expiresAt = null;
        if ($plan->billing_cycle === 'monthly') {
            $expiresAt = Carbon::now()->addMonth();
        } elseif ($plan->billing_cycle === 'yearly') {
            $expiresAt = Carbon::now()->addYear();
        }

        // Create new subscription
        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => Carbon::now(),
            'expires_at' => $expiresAt,
            'payment_details' => $paymentDetails,
        ]);
    }

    /**
     * Cancel user's subscription
     */
    public function cancelSubscription(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return false;
        }

        $subscription->cancel();
        return true;
    }

    /**
     * Renew user's subscription
     */
    public function renewSubscription(User $user, ?array $paymentDetails = null): ?UserSubscription
    {
        $currentSubscription = $this->getActiveSubscription($user);
        
        if (!$currentSubscription) {
            return null;
        }

        $plan = $currentSubscription->subscriptionPlan;

        // Calculate new expiry date
        $expiresAt = null;
        if ($plan->billing_cycle === 'monthly') {
            $expiresAt = Carbon::now()->addMonth();
        } elseif ($plan->billing_cycle === 'yearly') {
            $expiresAt = Carbon::now()->addYear();
        }

        // Update current subscription
        $currentSubscription->update([
            'expires_at' => $expiresAt,
            'payment_details' => $paymentDetails ? array_merge($currentSubscription->payment_details ?? [], $paymentDetails) : $currentSubscription->payment_details,
        ]);

        return $currentSubscription;
    }

    /**
     * Change user's subscription plan
     */
    public function changePlan(User $user, int $newPlanId, ?array $paymentDetails = null): UserSubscription
    {
        // Cancel current subscription and create new one
        $this->cancelSubscription($user);
        return $this->subscribe($user, $newPlanId, $paymentDetails);
    }

    /**
     * Get all available subscription plans
     */
    public function getAvailablePlans(): \Illuminate\Database\Eloquent\Collection
    {
        return SubscriptionPlan::active()->ordered()->get();
    }

    /**
     * Check and update expired subscriptions status
     * This is optional - the system validates based on expires_at in real-time
     * This method is useful for cleanup and reporting purposes
     */
    public function checkExpiredSubscriptions(): int
    {
        $expiredCount = 0;

        // Find subscriptions where status is 'active' but expires_at is in the past
        $expiredSubscriptions = UserSubscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            // Update status for cleanup and reporting
            // Note: Real-time validation uses expires_at directly, not status
            $subscription->update(['status' => 'expired']);
            $expiredCount++;
        }

        return $expiredCount;
    }
}

