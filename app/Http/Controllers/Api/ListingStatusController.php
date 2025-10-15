<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListingStatusRequest;
use App\Models\CarListing;
use App\Services\OdooService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingStatusController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OdooService $odooService
    ) {}

    /**
     * Pause a car listing
     */
    public function pause(ListingStatusRequest $request, int $listingId): JsonResponse
    {
        try {
            $listing = $this->getUserListing($request->user(), $listingId);
            
            if ($listing->status === 'paused') {
                return $this->errorResponse('Listing is already paused', 400);
            }

            DB::beginTransaction();

            // Update local database
            $listing->update([
                'status' => 'paused',
                'paused_at' => now()
            ]);

            // Sync with Odoo if listing has odoo_id
            if ($listing->odoo_id) {
                $this->syncStatusWithOdoo($listing, 'pause');
            }

            DB::commit();

            Log::info('Listing paused successfully', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'odoo_id' => $listing->odoo_id
            ]);

            return $this->successResponse([
                'listing' => $listing->fresh(),
                'status' => 'paused',
                'message' => 'Listing paused successfully'
            ], 'Listing paused successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to pause listing', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to pause listing: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Unpause a car listing
     */
    public function unpause(ListingStatusRequest $request, int $listingId): JsonResponse
    {
        try {
            $listing = $this->getUserListing($request->user(), $listingId);
            
            if ($listing->status !== 'paused') {
                return $this->errorResponse('Listing is not paused', 400);
            }

            DB::beginTransaction();

            // Update local database
            $listing->update([
                'status' => 'active',
                'paused_at' => null
            ]);

            // Sync with Odoo if listing has odoo_id
            if ($listing->odoo_id) {
                $this->syncStatusWithOdoo($listing, 'unpause');
            }

            DB::commit();

            Log::info('Listing unpaused successfully', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'odoo_id' => $listing->odoo_id
            ]);

            return $this->successResponse([
                'listing' => $listing->fresh(),
                'status' => 'active',
                'message' => 'Listing unpaused successfully'
            ], 'Listing unpaused successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to unpause listing', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to unpause listing: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Boost a car listing
     */
    public function boost(ListingStatusRequest $request, int $listingId): JsonResponse
    {
        try {
            $listing = $this->getUserListing($request->user(), $listingId);
            
            if ($listing->status !== 'active') {
                return $this->errorResponse('Only active listings can be boosted', 400);
            }

            DB::beginTransaction();

            // Update local database
            $listing->update([
                'is_promoted' => true,
                'promotion_started_at' => now(),
                'promotion_expires_at' => now()->addDays(7) // Default 7 days boost
            ]);

            // Sync with Odoo if listing has odoo_id
            if ($listing->odoo_id) {
                $this->syncStatusWithOdoo($listing, 'boost');
            }

            DB::commit();

            Log::info('Listing boosted successfully', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'odoo_id' => $listing->odoo_id
            ]);

            return $this->successResponse([
                'listing' => $listing->fresh(),
                'status' => 'boosted',
                'message' => 'Listing boosted successfully'
            ], 'Listing boosted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to boost listing', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to boost listing: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Archive a car listing
     */
    public function archive(ListingStatusRequest $request, int $listingId): JsonResponse
    {
        try {
            $listing = $this->getUserListing($request->user(), $listingId);
            
            if ($listing->status === 'archived') {
                return $this->errorResponse('Listing is already archived', 400);
            }

            DB::beginTransaction();

            // Update local database
            $listing->update([
                'status' => 'archived',
                'archived_at' => now()
            ]);

            // Sync with Odoo if listing has odoo_id
            if ($listing->odoo_id) {
                $this->syncStatusWithOdoo($listing, 'archive');
            }

            DB::commit();

            Log::info('Listing archived successfully', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'odoo_id' => $listing->odoo_id
            ]);

            return $this->successResponse([
                'listing' => $listing->fresh(),
                'status' => 'archived',
                'message' => 'Listing archived successfully'
            ], 'Listing archived successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to archive listing', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to archive listing: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark a car listing as sold
     */
    public function markSold(ListingStatusRequest $request, int $listingId): JsonResponse
    {
        try {
            $listing = $this->getUserListing($request->user(), $listingId);
            
            if ($listing->status === 'sold') {
                return $this->errorResponse('Listing is already marked as sold', 400);
            }

            DB::beginTransaction();

            // Update local database
            $listing->update([
                'status' => 'sold',
                'sold_at' => now()
            ]);

            // Sync with Odoo if listing has odoo_id
            if ($listing->odoo_id) {
                $this->syncStatusWithOdoo($listing, 'mark_sold');
            }

            DB::commit();

            Log::info('Listing marked as sold successfully', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'odoo_id' => $listing->odoo_id
            ]);

            return $this->successResponse([
                'listing' => $listing->fresh(),
                'status' => 'sold',
                'message' => 'Listing marked as sold successfully'
            ], 'Listing marked as sold successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to mark listing as sold', [
                'listing_id' => $listingId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to mark listing as sold: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's listing with authorization check
     */
    private function getUserListing($user, int $listingId): CarListing
    {
        $listing = CarListing::where('id', $listingId)
            ->where('user_id', $user->id)
            ->first();

        if (!$listing) {
            throw new \Exception('Listing not found or you do not have permission to modify this listing');
        }

        return $listing;
    }

    /**
     * Sync listing status with Odoo
     */
    private function syncStatusWithOdoo(CarListing $listing, string $action): void
    {
        try {
            $odooResponse = $this->odooService->updateListingStatus(
                $listing->odoo_id,
                $listing->id,
                $action,
                $listing->status
            );

            if (!$odooResponse['success']) {
                Log::warning('Failed to sync listing status with Odoo', [
                    'listing_id' => $listing->id,
                    'odoo_id' => $listing->odoo_id,
                    'action' => $action,
                    'error' => $odooResponse['message']
                ]);
            } else {
                Log::info('Listing status synced with Odoo successfully', [
                    'listing_id' => $listing->id,
                    'odoo_id' => $listing->odoo_id,
                    'action' => $action
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync listing status with Odoo', [
                'listing_id' => $listing->id,
                'odoo_id' => $listing->odoo_id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
}
