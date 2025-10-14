<?php

namespace App\Services;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\CarListing;
use App\Models\UserSlot;
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
     * FREE USERS: Use slot system (1 free slot per month + purchased slots)
     * DEALERS WITH "VERIFIED" STATUS: Use max_listings from their subscription plan
     * DEALERS WITH "APPROVED" STATUS: Cannot post yet (need to complete payment)
     * SUBSCRIBED USERS: Use max_listings from their subscription plan
     * SUSPENDED USERS: Cannot create listings even with available slots
     */
    public function canCreateListing(User $user): array
    {
        // First check if account is suspended
        if ($user->isSuspended()) {
            return [
                'can_create' => false,
                'reason' => 'Your account is suspended. Please renew your subscription to reactivate your account.',
                'code' => 'ACCOUNT_SUSPENDED',
                'suspended_at' => $user->suspended_at,
                'suspension_reason' => $user->suspension_reason,
            ];
        }

        // Check if user is a dealer
        if ($user->is_dealer) {
            $dealerApplication = $user->activeDealerApplication;
            
            // Dealer must have verified status (payment completed)
            if (!$dealerApplication || !$dealerApplication->isVerified()) {
                return [
                    'can_create' => false,
                    'reason' => $dealerApplication && $dealerApplication->isApproved() 
                        ? 'Your dealer application is approved but you need to complete payment first to start posting listings.'
                        : 'Your dealer account is not verified. Please complete the verification process.',
                    'code' => 'DEALER_NOT_VERIFIED',
                    'dealer_status' => $dealerApplication?->status?->name ?? 'no_application',
                    'payment_completed' => $dealerApplication?->payment_completed ?? false,
                ];
            }
        }

        // Check if user has active subscription (including grace period)
        if (!$this->hasActiveSubscription($user)) {
            // Free tier: Use slot system
            return $this->checkFreeTierSlots($user);
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

        // Check if subscription is in grace period
        $subscription = $this->getActiveSubscription($user);
        $inGracePeriod = $subscription && $subscription->isInGracePeriod();
        $gracePeriodInfo = [];
        
        if ($inGracePeriod) {
            $gracePeriodInfo = [
                'in_grace_period' => true,
                'grace_period_days_remaining' => $subscription->grace_period_days_remaining,
                'grace_period_ends_at' => $subscription->grace_period_ends_at,
                'warning' => "Your subscription has expired. You have {$subscription->grace_period_days_remaining} day(s) remaining to renew before your account is suspended.",
            ];
        }

        return array_merge([
            'can_create' => true,
            'current_listings' => $currentListingsCount,
            'max_listings' => $plan->max_listings,
            'remaining_listings' => $plan->max_listings ? ($plan->max_listings - $currentListingsCount) : null,
            'plan_name' => $plan->name
        ], $gracePeriodInfo);
    }

    /**
     * Check free tier slots
     * Free users get 1 slot per month + any purchased slots
     * Slots are consumed when creating listings and stay consumed even if listing is deleted
     */
    private function checkFreeTierSlots(User $user): array
    {
        // Get or create user slot record
        $userSlot = $user->userSlot;
        
        if (!$userSlot) {
            // Create initial slot record for new free user
            $userSlot = UserSlot::create([
                'user_id' => $user->id,
                'total_slots' => 1,
                'consumed_slots' => 0,
                'available_slots' => 1,
                'purchased_slots' => 0,
                'has_monthly_free_slot' => true,
                'last_free_slot_reset_at' => now(),
                'next_free_slot_reset_at' => now()->addMonth()->startOfMonth(),
            ]);
        }

        // Check if monthly free slot needs reset
        if ($userSlot->shouldResetMonthlyFreeSlot()) {
            $userSlot->resetMonthlyFreeSlot();
            $userSlot->refresh();
        }

        // Check if user has available slots
        if ($userSlot->available_slots <= 0) {
            $daysUntilReset = $userSlot->days_until_next_free_slot;
            $message = "You have used all your available slots. ";
            
            if ($daysUntilReset > 0) {
                $message .= "Your free monthly slot will be available in {$daysUntilReset} day(s). ";
            }
            
            $message .= "You can purchase additional slots or subscribe to a plan for more listings.";

            return [
                'can_create' => false,
                'reason' => $message,
                'code' => 'NO_SLOTS_AVAILABLE',
                'slots' => $userSlot->slot_breakdown,
                'plan_name' => 'Free'
            ];
        }

        return [
            'can_create' => true,
            'slots' => $userSlot->slot_breakdown,
            'plan_name' => 'Free'
        ];
    }

    /**
     * Consume a slot when free user creates a listing
     * Should be called after listing is successfully created
     */
    public function consumeSlot(User $user): bool
    {
        // Only consume slots for free users (no active subscription)
        if ($this->hasActiveSubscription($user)) {
            return true; // Subscribed users don't use slots
        }

        $userSlot = $user->userSlot;
        
        if (!$userSlot) {
            return false;
        }

        return $userSlot->consumeSlot();
    }

    /**
     * Get subscription limits for a user
     * Includes slot info for free tier
     */
    public function getSubscriptionLimits(User $user): array
    {
        // Free tier - use slot system
        if (!$this->hasActiveSubscription($user)) {
            $userSlot = $user->userSlot;
            
            if (!$userSlot) {
                // Create initial slot record
                $userSlot = UserSlot::create([
                    'user_id' => $user->id,
                    'total_slots' => 1,
                    'consumed_slots' => 0,
                    'available_slots' => 1,
                    'purchased_slots' => 0,
                    'has_monthly_free_slot' => true,
                    'last_free_slot_reset_at' => now(),
                    'next_free_slot_reset_at' => now()->addMonth()->startOfMonth(),
                ]);
            }

            // Check if monthly free slot needs reset
            if ($userSlot->shouldResetMonthlyFreeSlot()) {
                $userSlot->resetMonthlyFreeSlot();
                $userSlot->refresh();
            }
            
            return [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'uses_slots' => true,
                'slots' => $userSlot->slot_breakdown,
                'max_photos' => 5,
                'features' => []
            ];
        }

        $plan = $this->getSubscriptionPlan($user);
        
        if (!$plan) {
            return [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'uses_slots' => true,
                'slots' => [
                    'monthly_free' => 1,
                    'purchased' => 0,
                    'total' => 1,
                    'consumed' => 0,
                    'available' => 1,
                ],
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
            'uses_slots' => false,
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
     * Expiration date is calculated based on plan's billing_cycle from database
     */
    public function subscribe(User $user, int $subscriptionPlanId, ?array $paymentDetails = null): UserSubscription
    {
        $plan = SubscriptionPlan::findOrFail($subscriptionPlanId);

        // Cancel any existing active subscription
        $existingSubscription = $this->getActiveSubscription($user);
        if ($existingSubscription) {
            $existingSubscription->cancel();
        }

        // Calculate expiry date based on plan's billing_cycle from database
        // Reads from subscription_plans.billing_cycle column (monthly/yearly)
        $expiresAt = $plan->calculateExpirationDate();

        // Unsuspend account if it was suspended
        if ($user->isSuspended()) {
            $user->unsuspend();
        }

        // Create new subscription
        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => Carbon::now(),
            'expires_at' => $expiresAt,
            'grace_period_days' => 15, // Default 15-day grace period
            'payment_details' => $paymentDetails,
        ]);

        // Auto-create default studio for dealers
        if ($plan->name === 'Dealer') {
            $this->createDefaultStudioForDealer($user);
            
            // Process existing listings for new dealers
            $autoStudioService = app(\App\Services\AutoStudioService::class);
            $autoStudioService->processExistingDealerListings($user);
        }

        return $subscription;
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
     * Expiration date is calculated based on plan's billing_cycle from database
     */
    public function renewSubscription(User $user, ?array $paymentDetails = null): ?UserSubscription
    {
        $currentSubscription = $this->getActiveSubscription($user);
        
        if (!$currentSubscription) {
            return null;
        }

        $plan = $currentSubscription->subscriptionPlan;

        // Calculate new expiry date based on plan's billing_cycle from database
        // Reads from subscription_plans.billing_cycle column (monthly/yearly)
        $expiresAt = $plan->calculateExpirationDate();

        // Unsuspend account if it was suspended
        if ($user->isSuspended()) {
            $user->unsuspend();
        }

        // Update current subscription
        $currentSubscription->update([
            'expires_at' => $expiresAt,
            'status' => 'active',
            'is_in_grace_period' => false,
            'grace_period_ends_at' => null,
            'grace_period_days' => 15, // Reset grace period
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
     * Create default studio for dealer
     */
    protected function createDefaultStudioForDealer(User $user): void
    {
        // Check if dealer already has a default studio
        $existingStudio = \App\Models\DealerStudio::where('dealer_id', $user->id)
            ->where('name', 'Default Studio')
            ->first();

        if ($existingStudio) {
            return;
        }

        // Create default studio
        $studio = \App\Models\DealerStudio::create([
            'dealer_id' => $user->id,
            'name' => 'Default Studio',
            'description' => 'Default studio for ' . $user->full_name,
            'address' => $user->address ?? 'Address not provided',
            'city_id' => $user->city_id ?? 1, // Default city or first city
            'phone' => $user->phone,
            'email' => $user->email,
            'is_active' => true,
            'is_verified' => true, // Auto-verify default studio
            'verification_status' => 'verified',
            'verified_at' => now(),
            'settings' => [
                'auto_assign_listings' => true,
                'auto_featured' => true,
                'is_default' => true,
            ],
            'business_hours' => [
                'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                'tuesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                'wednesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                'thursday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                'friday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                'saturday' => ['is_open' => true, 'open' => '09:00', 'close' => '17:00'],
                'sunday' => ['is_open' => false, 'open' => '09:00', 'close' => '17:00'],
            ],
            'amenities' => ['Showroom', 'Parking', 'Customer Service'],
            'specialties' => ['All Vehicle Types'],
        ]);

        // Auto-associate existing car listings with default studio
        $this->autoAssociateListingsWithStudio($user, $studio->id);

        // Auto-promote dealer's listings to top picks
        $this->autoPromoteDealerListings($user);
    }

    /**
     * Auto-associate existing car listings with studio
     */
    protected function autoAssociateListingsWithStudio(User $user, int $studioId): void
    {
        \App\Models\CarListing::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('studio_id')
            ->update(['studio_id' => $studioId]);
    }

    /**
     * Auto-promote dealer's listings to top picks
     */
    protected function autoPromoteDealerListings(User $user): void
    {
        \App\Models\CarListing::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('is_featured', false)
            ->update([
                'is_featured' => true,
                'featured_at' => now(),
            ]);
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

