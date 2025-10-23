<?php

namespace App\Services;

use App\Models\User;
use App\Models\CarListing;
use App\Models\DealerStudio;
use App\Services\AutoStudioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DealerManagementService
{
    protected AutoStudioService $autoStudioService;

    public function __construct(AutoStudioService $autoStudioService)
    {
        $this->autoStudioService = $autoStudioService;
    }

    /**
     * Make a user a dealer by setting is_dealer = 1
     * COMMENTED OUT: Now using approved dealer applications
     */
    public function makeUserDealer(User $user): bool
    {
        // COMMENTED OUT: Manual dealer assignment
        // try {
        //     DB::beginTransaction();
        //
        //     // Set is_dealer = 1
        //     $user->markAsDealer();
        //
        //     // Process existing listings for the new dealer
        //     $this->autoStudioService->processExistingDealerListings($user);
        //
        //     DB::commit();
        //
        //     Log::info('User marked as dealer', [
        //         'user_id' => $user->id,
        //         'email' => $user->email,
        //         'is_dealer' => $user->is_dealer
        //     ]);
        //
        //     return true;
        //
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     Log::error('Failed to make user dealer', [
        //         'user_id' => $user->id,
        //         'error' => $e->getMessage()
        //     ]);
        //     return false;
        // }

        // SIMPLIFIED: User becomes dealer when their application is approved
        // This is handled automatically in the dealer application approval process
        return $user->isDealer();
    }

    /**
     * Remove dealer status from user
     */
    public function removeDealerStatus(User $user): bool
    {
        try {
            DB::beginTransaction();

            // Remove dealer status
            $user->removeDealerStatus();

            // Optionally remove featured status from their listings
            CarListing::where('user_id', $user->id)
                ->where('is_featured', true)
                ->update([
                    'is_featured' => false,
                    'featured_at' => null
                ]);

            DB::commit();

            Log::info('Dealer status removed from user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'is_dealer' => $user->is_dealer
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove dealer status', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user is a dealer using is_dealer field
     */
    public function isDealer(User $user): bool
    {
        return $user->isDealer();
    }

    /**
     * Get all dealers
     * COMMENTED OUT: Now using approved dealer applications
     */
    public function getAllDealers()
    {
        // COMMENTED OUT: Using is_dealer field
        // return User::where('is_dealer', 1)->get();

        // SIMPLIFIED: Get users with approved dealer applications
        return User::whereHas('dealerApplications', function ($query) {
            $query->approved();
        })->get();
    }

    /**
     * Get dealer statistics
     * COMMENTED OUT: Now using approved dealer applications
     */
    public function getDealerStatistics(User $dealer): array
    {
        if (!$this->isDealer($dealer)) {
            return [];
        }

        $studios = $dealer->studios();
        $listings = CarListing::where('user_id', $dealer->id);

        return [
            'is_dealer' => $dealer->isDealer(),
            'has_approved_application' => $dealer->hasApprovedDealerApplication(),
            'studios_count' => $studios->count(),
            'total_listings' => $listings->count(),
            'active_listings' => $listings->where('status', 'active')->count(),
            'featured_listings' => $listings->where('is_featured', true)->count(),
            'sponsored_listings' => $listings->where('is_sponsored', true)->count(),
        ];
    }

    /**
     * Bulk make users dealers
     */
    public function bulkMakeDealers(array $userIds): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            if (!$user) {
                $results['failed'][] = [
                    'user_id' => $userId,
                    'reason' => 'User not found'
                ];
                continue;
            }

            if ($this->makeUserDealer($user)) {
                $results['success'][] = [
                    'user_id' => $userId,
                    'email' => $user->email
                ];
            } else {
                $results['failed'][] = [
                    'user_id' => $userId,
                    'email' => $user->email,
                    'reason' => 'Failed to process'
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk remove dealer status
     */
    public function bulkRemoveDealerStatus(array $userIds): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            if (!$user) {
                $results['failed'][] = [
                    'user_id' => $userId,
                    'reason' => 'User not found'
                ];
                continue;
            }

            if ($this->removeDealerStatus($user)) {
                $results['success'][] = [
                    'user_id' => $userId,
                    'email' => $user->email
                ];
            } else {
                $results['failed'][] = [
                    'user_id' => $userId,
                    'email' => $user->email,
                    'reason' => 'Failed to process'
                ];
            }
        }

        return $results;
    }
}
