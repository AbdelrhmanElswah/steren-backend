<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\ListingApprovalRequest;
use App\Services\ListingApprovalService;
use App\Models\CarListing;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class ListingApprovalController extends Controller
{
    use ApiResponse;

    protected ListingApprovalService $listingApprovalService;

    public function __construct(ListingApprovalService $listingApprovalService)
    {
        $this->listingApprovalService = $listingApprovalService;
    }

    public function approve(Request $request, int $listingId)
    {
        try {
            $result = $this->listingApprovalService->approveListing(
                $listingId,
                $request->user()?->id
            );

            return $this->successResponse($result, 'Listing approved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to approve listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function reject(ListingApprovalRequest $request, int $listingId)
    {
        try {
            $validated = $request->validated();
            
            $result = $this->listingApprovalService->rejectListing(
                $listingId,
                $validated['reason'] ?? 'No reason provided',
                $request->user()?->id
            );

            return $this->successResponse($result, 'Listing rejected successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to reject listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function process(ListingApprovalRequest $request, int $listingId)
    {
        try {
            $validated = $request->validated();
            $action = $validated['action'];
            $reason = $validated['reason'] ?? null;
            $operatorId = $request->user()?->id;

            $result = $this->listingApprovalService->processListing($listingId, $action, $reason, $operatorId);

            return $this->successResponse($result, "Listing {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$validated['action']} listing: " . $e->getMessage(),
                500
            );
        }
    }

    public function approveByOdooId(Request $request, string $odooId)
    {
        try {
            $result = $this->listingApprovalService->approveListingByOdooId(
                $odooId,
                $request->user()?->id
            );

            return $this->successResponse($result, 'Listing approved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to approve listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function rejectByOdooId(ListingApprovalRequest $request, string $odooId)
    {
        try {
            $validated = $request->validated();
            
            $result = $this->listingApprovalService->rejectListingByOdooId(
                $odooId,
                $validated['reason'] ?? 'No reason provided',
                $request->user()?->id
            );

            return $this->successResponse($result, 'Listing rejected successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to reject listing: ' . $e->getMessage(),
                500
            );
        }
    }

    public function processByOdooId(ListingApprovalRequest $request, string $odooId)
    {
        try {
            $validated = $request->validated();
            $action = $validated['action'];
            $reason = $validated['reason'] ?? null;
            $operatorId = $request->user()?->id;

            $result = $this->listingApprovalService->processListingByOdooId($odooId, $action, $reason, $operatorId);

            return $this->successResponse($result, "Listing {$action}d successfully");

        } catch (Exception $e) {
            return $this->errorResponse(
                "Failed to {$validated['action']} listing: " . $e->getMessage(),
                500
            );
        }
    }
}

