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
     * Check if user is a dealer using is_dealer field
     */
    protected function isDealer(User $user): bool
    {
        // Check if user has is_dealer = 1 (true)
        return $user->isDealer();
    }

    /**
     * Auto-associate car listing with dealer's default studio
     */
    public function autoAssociateListingWithStudio(CarListing $listing): void
    {
        $user = $listing->user;
        
        // Only for dealers
        if (!$this->isDealer($user)) {
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
        
        // Only for dealers
        if (!$this->isDealer($user)) {
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
     * Checks for any active studio first, then creates "Default Studio" if none exists
     */
    protected function getOrCreateDefaultStudio(User $user): ?DealerStudio
    {
        // Check if dealer already has any active studio (not just "Default Studio")
        $existingStudio = DealerStudio::where('dealer_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($existingStudio) {
            return $existingStudio;
        }

        // TODO: Temporarily disabled - DealerStudio creation commented out for performance
        // Create default studio only if no active studio exists
        // return DealerStudio::create([
        //     'dealer_id' => $user->id,
        //     'name' => 'Default Studio',
        //     'description' => 'Default studio for ' . $user->full_name,
        //     'address' => $user->address ?? 'Address not provided',
        //     'city_id' => $user->city_id ?? 1, // Default city or first city
        //     'phone' => $user->phone,
        //     'email' => $user->email,
        //     'is_active' => true,
        //     'is_verified' => true, // Auto-verify default studio
        //     'verification_status' => 'verified',
        //     'verified_at' => now(),
        //     'settings' => [
        //         'auto_assign_listings' => true,
        //         'auto_featured' => true,
        //         'is_default' => true,
        //     ],
        //     'business_hours' => [
        //         'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
        //         'tuesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
        //         'wednesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
        //         'thursday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
        //         'friday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
        //         'saturday' => ['is_open' => true, 'open' => '09:00', 'close' => '17:00'],
        //         'sunday' => ['is_open' => false, 'open' => '09:00', 'close' => '17:00'],
        //     ],
        //     'amenities' => ['Showroom', 'Parking', 'Customer Service'],
        //     'specialties' => ['All Vehicle Types'],
        // ]);
        
        return null;
    }

    /**
     * Process new car listing for dealer
     */
    public function processNewDealerListing(CarListing $listing): void
    {
        $user = $listing->user;
        
        // Check if user is a dealer (either by subscription or approved application)
        if (!$this->isDealer($user)) {
            return;
        }

        // Update listing in one go to avoid multiple observer triggers
        $updateData = [];
        
        // Auto-associate with default studio (if exists)
        $defaultStudio = $this->getOrCreateDefaultStudio($user);
        if ($defaultStudio) {
            $updateData['studio_id'] = $defaultStudio->id;
        }

        // Auto-promote to featured (top picks) if not already featured
        if (!$listing->is_featured) {
            $updateData['is_featured'] = true;
            $updateData['featured_at'] = now();
        }

        // Update all at once if there are changes
        if (!empty($updateData)) {
            $listing->update($updateData);
        }
    }

    /**
     * Process existing dealer listings when they become dealer
     */
    public function processExistingDealerListings(User $user): void
    {
        // Only for dealers
        if (!$this->isDealer($user)) {
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
