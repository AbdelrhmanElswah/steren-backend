<?php

namespace App\Services;

use App\Models\User;
use App\Models\CarListing;
use App\Models\DealerStudio;
use App\Services\SubscriptionService;

class AutoStudioService
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Auto-associate car listing with dealer's default studio
     */
    public function autoAssociateListingWithStudio(CarListing $listing): void
    {
        $user = $listing->user;
        $plan = $this->subscriptionService->getSubscriptionPlan($user);

        // Only for dealers
        if (!$plan || $plan->name !== 'Dealer') {
            return;
        }

        // Get or create default studio
        $defaultStudio = $this->getOrCreateDefaultStudio($user);

        if ($defaultStudio) {
            $listing->update(['studio_id' => $defaultStudio->id]);
        }
    }

    /**
     * Auto-promote dealer's listing to top picks
     */
    public function autoPromoteListingToTopPicks(CarListing $listing): void
    {
        $user = $listing->user;
        $plan = $this->subscriptionService->getSubscriptionPlan($user);

        // Only for dealers
        if (!$plan || $plan->name !== 'Dealer') {
            return;
        }

        // Auto-promote to featured (top picks)
        if (!$listing->is_featured) {
            $listing->update([
                'is_featured' => true,
                'featured_at' => now(),
            ]);
        }
    }

    /**
     * Get or create default studio for dealer
     */
    protected function getOrCreateDefaultStudio(User $user): ?DealerStudio
    {
        // Check if dealer already has a default studio
        $defaultStudio = DealerStudio::where('dealer_id', $user->id)
            ->where('name', 'Default Studio')
            ->first();

        if ($defaultStudio) {
            return $defaultStudio;
        }

        // Create default studio
        return DealerStudio::create([
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
    }

    /**
     * Process new car listing for dealer
     */
    public function processNewDealerListing(CarListing $listing): void
    {
        $user = $listing->user;
        $plan = $this->subscriptionService->getSubscriptionPlan($user);

        // Only for dealers
        if (!$plan || $plan->name !== 'Dealer') {
            return;
        }

        // Auto-associate with default studio
        $this->autoAssociateListingWithStudio($listing);

        // Auto-promote to top picks
        $this->autoPromoteListingToTopPicks($listing);
    }

    /**
     * Process existing dealer listings when they become dealer
     */
    public function processExistingDealerListings(User $user): void
    {
        $plan = $this->subscriptionService->getSubscriptionPlan($user);

        // Only for dealers
        if (!$plan || $plan->name !== 'Dealer') {
            return;
        }

        // Get or create default studio
        $defaultStudio = $this->getOrCreateDefaultStudio($user);

        if ($defaultStudio) {
            // Auto-associate existing listings with default studio
            CarListing::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('studio_id')
                ->update(['studio_id' => $defaultStudio->id]);

            // Auto-promote existing listings to top picks
            CarListing::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('is_featured', false)
                ->update([
                    'is_featured' => true,
                    'featured_at' => now(),
                ]);
        }
    }
}
