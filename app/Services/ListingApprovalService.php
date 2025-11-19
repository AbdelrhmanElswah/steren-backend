<?php

namespace App\Services;

use App\Models\CarListing;
use App\Services\OdooService;
use App\Exceptions\OdooServiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ListingApprovalService
{
    public function __construct(
        private OdooService $odooService
    ) {}

    public function approveListing(int $listingId, ?int $approvedBy = null): array
    {
        try {
            $listing = CarListing::findOrFail($listingId);
            
            if ($listing->operator_decision === 'approved') {
                throw new OdooServiceException('Listing is already approved');
            }

            if (!$listing->odoo_id) {
                throw new OdooServiceException('Listing does not have an Odoo ID. Cannot approve without Odoo integration.');
            }

            DB::beginTransaction();

            $odooResponse = $this->odooService->approveListing($listing->odoo_id, $listing->id);

            if (!$odooResponse['success']) {
                throw new OdooServiceException('Failed to notify Odoo about approval: ' . $odooResponse['message']);
            }

            $listing->update([
                'operator_decision' => 'approved',
                'status' => 'active',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null
            ]);

            DB::commit();

            Log::info('Listing approved', [
                'listing_id' => $listingId,
                'odoo_id' => $listing->odoo_id,
                'approved_by' => $approvedBy,
                'odoo_response' => $odooResponse
            ]);

            return [
                'success' => true,
                'message' => 'Listing approved successfully',
                'listing_id' => $listingId,
                'odoo_id' => $listing->odoo_id,
                'status' => 'active',
                'odoo_notified' => true
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve listing', [
                'listing_id' => $listingId,
                'error' => $e->getMessage()
            ]);

            throw new OdooServiceException('Failed to approve listing: ' . $e->getMessage());
        }
    }

    public function rejectListing(int $listingId, string $reason, ?int $rejectedBy = null): array
    {
        try {
            $listing = CarListing::findOrFail($listingId);
            
            if ($listing->operator_decision === 'rejected') {
                throw new OdooServiceException('Listing is already rejected');
            }

            if (!$listing->odoo_id) {
                throw new OdooServiceException('Listing does not have an Odoo ID. Cannot reject without Odoo integration.');
            }

            DB::beginTransaction();

            $odooResponse = $this->odooService->rejectListing($listing->odoo_id, $listing->id, $reason);

            if (!$odooResponse['success']) {
                throw new OdooServiceException('Failed to notify Odoo about rejection: ' . $odooResponse['message']);
            }

            $listing->update([
                'operator_decision' => 'rejected',
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'approved_by' => null,
                'approved_at' => null
            ]);

            DB::commit();

            Log::info('Listing rejected', [
                'listing_id' => $listingId,
                'odoo_id' => $listing->odoo_id,
                'reason' => $reason,
                'rejected_by' => $rejectedBy,
                'odoo_response' => $odooResponse
            ]);

            return [
                'success' => true,
                'message' => 'Listing rejected successfully',
                'listing_id' => $listingId,
                'odoo_id' => $listing->odoo_id,
                'status' => 'rejected',
                'reason' => $reason,
                'odoo_notified' => true
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject listing', [
                'listing_id' => $listingId,
                'error' => $e->getMessage()
            ]);

            throw new OdooServiceException('Failed to reject listing: ' . $e->getMessage());
        }
    }

    public function approveListingByOdooId(string $odooId, ?int $approvedBy = null): array
    {
        try {
            $listing = CarListing::where('odoo_id', $odooId)->firstOrFail();
            
            if ($listing->operator_decision === 'approved') {
                throw new OdooServiceException('Listing is already approved');
            }

            DB::beginTransaction();

            $listing->update([
                'operator_decision' => 'approved',
                'status' => 'active',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null
            ]);

            DB::commit();

            Log::info('Listing approved via Odoo integration', [
                'listing_id' => $listing->id,
                'odoo_id' => $odooId,
                'approved_by' => $approvedBy
            ]);

            return [
                'success' => true,
                'listing_id' => $listing->id,
                'odoo_id' => $odooId,
                'status' => 'active',
                'operator_decision' => 'approved'
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve listing via Odoo', [
                'odoo_id' => $odooId,
                'error' => $e->getMessage()
            ]);
            throw new OdooServiceException('Failed to approve listing: ' . $e->getMessage());
        }
    }

    public function rejectListingByOdooId(string $odooId, string $reason, ?int $rejectedBy = null): array
    {
        try {
            // Optimize query - only select needed fields
            $listing = CarListing::select('id', 'odoo_id', 'operator_decision', 'status', 'rejected_by', 'rejected_at', 'rejection_reason', 'approved_by', 'approved_at')
                ->where('odoo_id', $odooId)
                ->firstOrFail();
            
            if ($listing->operator_decision === 'rejected') {
                throw new OdooServiceException('Listing is already rejected');
            }

            // Disable events to skip observer (performance optimization)
            // Removed DB transaction - single update doesn't need transaction overhead
            CarListing::withoutEvents(function () use ($listing, $reason, $rejectedBy) {
                $listing->update([
                    'operator_decision' => 'rejected',
                    'status' => 'rejected',
                    'rejected_by' => $rejectedBy,
                    'rejected_at' => now(),
                    'rejection_reason' => $reason,
                    'approved_by' => null,
                    'approved_at' => null
                ]);
            });

            Log::info('Listing rejected via Odoo integration', [
                'listing_id' => $listing->id,
                'odoo_id' => $odooId,
                'rejected_by' => $rejectedBy,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'listing_id' => $listing->id,
                'odoo_id' => $odooId,
                'status' => 'rejected',
                'operator_decision' => 'rejected'
            ];

        } catch (Exception $e) {
            Log::error('Failed to reject listing via Odoo', [
                'odoo_id' => $odooId,
                'error' => $e->getMessage()
            ]);
            throw new OdooServiceException('Failed to reject listing: ' . $e->getMessage());
        }
    }

    public function processListing(int $listingId, string $action, ?string $reason = null, ?int $operatorId = null): array
    {
        if ($action === 'approve') {
            return $this->approveListing($listingId, $operatorId);
        } else {
            return $this->rejectListing($listingId, $reason ?? 'No reason provided', $operatorId);
        }
    }

    public function processListingByOdooId(string $odooId, string $action, ?string $reason = null, ?int $operatorId = null): array
    {
        $listing = CarListing::where('odoo_id', $odooId)->firstOrFail();
        return $this->processListing($listing->id, $action, $reason, $operatorId);
    }
}

