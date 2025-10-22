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
     * ALL USERS: Use 20-slot system (max 20 active listings)
     * DEALERS WITH "VERIFIED" STATUS: Can create up to 20 listings
     * DEALERS WITH "APPROVED" STATUS: Cannot post yet (need to complete payment)
     * SUBSCRIBED USERS: Can create up to 20 listings (subscription benefits still apply)
     * SUSPENDED USERS: Cannot create listings
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
            
            // TEMPORARY: Skip payment requirement until payment system is implemented
            // Dealer must have approved status (payment step skipped for now)
            if (!$dealerApplication || !$dealerApplication->isApproved()) {
                return [
                    'can_create' => false,
                    'reason' => 'Your dealer account is not approved. Please complete the verification process.',
                    'code' => 'DEALER_NOT_APPROVED',
                    'dealer_status' => $dealerApplication?->status?->name ?? 'no_application',
                    'payment_skipped' => true, // Indicates payment step is skipped
                ];
            }
        }

        // NEW 20-SLOT SYSTEM: All users can create up to 20 listings
        return $this->checkTwentySlotSystem($user);
    }

    /**
     * Check 20-slot system for all users
     * Users can create up to 20 active listings
     * When they reach 20, they must delete one to create a new one
     */
    private function checkTwentySlotSystem(User $user): array
    {
        // Count only ACTIVE listings (not soft-deleted ones)
        $currentListingsCount = CarListing::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'rejected'])
            ->count();

        $maxListings = 20;

        // Check if user has reached the 20 listing limit
        if ($currentListingsCount >= $maxListings) {
            return [
                'can_create' => false,
                'reason' => "You have reached your listing limit of {$maxListings} listings. Please delete one of your existing listings to create a new one.",
                'code' => 'LISTING_LIMIT_REACHED',
                'current_listings' => $currentListingsCount,
                'max_listings' => $maxListings,
                'remaining_listings' => 0
            ];
        }

        // Check if user has active subscription for additional benefits
        $subscriptionInfo = [];
        if ($this->hasActiveSubscription($user)) {
            $plan = $this->getSubscriptionPlan($user);
            $subscription = $this->getActiveSubscription($user);
            
            $subscriptionInfo = [
                'has_subscription' => true,
                'plan_name' => $plan?->name ?? 'Unknown',
                'subscription_benefits' => [
                    'priority_support' => $plan?->is_priority_support ?? false,
                    'analytics' => $plan?->is_analytics ?? false,
                    'badge' => $plan?->is_badge ?? false,
                    'search' => $plan?->is_search ?? false,
                    'similar_cars' => $plan?->is_similar_cars ?? false,
                    'top_picks' => $plan?->is_top_picks ?? false,
                    'you_may_also_like' => $plan?->is_you_may_also_like ?? false,
                ]
            ];

            // Check if subscription is in grace period
            if ($subscription && $subscription->isInGracePeriod()) {
                $subscriptionInfo['grace_period'] = [
                    'in_grace_period' => true,
                    'grace_period_days_remaining' => $subscription->grace_period_days_remaining,
                    'grace_period_ends_at' => $subscription->grace_period_ends_at,
                    'warning' => "Your subscription has expired. You have {$subscription->grace_period_days_remaining} day(s) remaining to renew before your account is suspended.",
                ];
            }
        } else {
            $subscriptionInfo = [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'subscription_benefits' => []
            ];
        }

        return array_merge([
            'can_create' => true,
            'current_listings' => $currentListingsCount,
            'max_listings' => $maxListings,
            'remaining_listings' => $maxListings - $currentListingsCount,
            'uses_slots' => false, // Not using the old slot system
            'slot_system' => 'twenty_listings' // New system identifier
        ], $subscriptionInfo);
    }

    /**
     * Get or create user slot record safely
     */
    private function getOrCreateUserSlot(User $user): UserSlot
    {
        // Use firstOrCreate to handle race conditions
        return UserSlot::firstOrCreate(
            ['user_id' => $user->id],
            [
                'total_slots' => 1,
                'consumed_slots' => 0,
                'available_slots' => 1,
                'purchased_slots' => 0,
                'has_monthly_free_slot' => true,
                'last_free_slot_reset_at' => now(),
                'next_free_slot_reset_at' => now()->addMonth()->startOfMonth(),
            ]
        );
    }

    /**
     * Check free tier slots
     * Free users get 1 slot per month + any purchased slots
     * Slots are consumed when creating listings and stay consumed even if listing is deleted
     */
    private function checkFreeTierSlots(User $user): array
    {
        // Get or create user slot record safely
        $userSlot = $this->getOrCreateUserSlot($user);
        $userSlot->refresh();

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
     * Consume a slot when user creates a listing
     * With the new 20-slot system, no slot consumption is needed
     * This method is kept for backward compatibility
     */
    public function consumeSlot(User $user): bool
    {
        // NEW SYSTEM: No slot consumption needed
        // Users can create up to 20 listings without consuming slots
        // When they reach 20, they must delete one to create a new one
        return true;
    }

    /**
     * Get subscription limits for a user
     * NEW SYSTEM: All users get 20 listings max
     */
    public function getSubscriptionLimits(User $user): array
    {
        // Count current active listings
        $currentListingsCount = CarListing::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'rejected'])
            ->count();

        $maxListings = 20;

        // Base limits for all users
        $baseLimits = [
            'has_subscription' => false,
            'plan_name' => 'Free',
            'uses_slots' => false,
            'slot_system' => 'twenty_listings',
            'max_listings' => $maxListings,
            'current_listings' => $currentListingsCount,
            'remaining_listings' => max(0, $maxListings - $currentListingsCount),
            'max_photos' => 5,
            'features' => []
        ];

        // Add subscription benefits if user has active subscription
        if ($this->hasActiveSubscription($user)) {
            $plan = $this->getSubscriptionPlan($user);
            
            if ($plan) {
                $baseLimits['has_subscription'] = true;
                $baseLimits['plan_name'] = $plan->name;
                $baseLimits['max_photos'] = $plan->max_photos ?? 5;
                $baseLimits['features'] = [
                    'whatsapp_phone' => $plan->is_whatsapp_phone ?? false,
                    'badge' => $plan->is_badge ?? false,
                    'search' => $plan->is_search ?? false,
                    'similar_cars' => $plan->is_similar_cars ?? false,
                    'dedicated_account_rep' => $plan->is_dedicated_account_rep ?? false,
                    'analytics' => $plan->is_analytics ?? false,
                    'top_picks' => $plan->is_top_picks ?? false,
                    'priority_support' => $plan->is_priority_support ?? false,
                    'you_may_also_like' => $plan->is_you_may_also_like ?? false,
                ];

                // Check grace period
                $subscription = $this->getActiveSubscription($user);
                if ($subscription && $subscription->isInGracePeriod()) {
                    $baseLimits['grace_period'] = [
                        'in_grace_period' => true,
                        'grace_period_days_remaining' => $subscription->grace_period_days_remaining,
                        'grace_period_ends_at' => $subscription->grace_period_ends_at,
                        'warning' => "Your subscription has expired. You have {$subscription->grace_period_days_remaining} day(s) remaining to renew before your account is suspended.",
                    ];
                }
            }
        }

        return $baseLimits;
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

