<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Models\CarListingEdit;
use App\Services\CarListingEditService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CarListingEditController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarListingEditService $carListingEditService
    ) {}

    /**
     * Approve car listing edit by Odoo ID
     */
    public function approveByOdooId(string $odooId, Request $request)
    {
        try {
            // Find edit by odoo_id
            $edit = CarListingEdit::where('odoo_id', $odooId)->firstOrFail();
            
            // Approve the edit
            $carListing = $this->carListingEditService->approveEdit($edit);
            
            Log::info('Car listing edit approved by Odoo', [
                'edit_id' => $edit->id,
                'odoo_id' => $odooId,
                'car_listing_id' => $carListing->id
            ]);
            
            return $this->successResponse([
                'car_listing' => $carListing,
                'edit' => $edit
            ], 'Car listing edit approved successfully');
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse(
                "Car listing edit with Odoo ID '{$odooId}' not found",
                404
            );
        } catch (\Exception $e) {
            Log::error('Failed to approve car listing edit: ' . $e->getMessage(), [
                'odoo_id' => $odooId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to approve car listing edit: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Reject car listing edit by Odoo ID
     */
    public function rejectByOdooId(string $odooId, Request $request)
    {
        try {
            // Find edit by odoo_id
            $edit = CarListingEdit::where('odoo_id', $odooId)->firstOrFail();
            
            // Get rejection reason from request
            $reason = $request->input('reason');
            
            // Reject the edit
            $carListing = $this->carListingEditService->rejectEdit($edit, $reason);
            
            Log::info('Car listing edit rejected by Odoo', [
                'edit_id' => $edit->id,
                'odoo_id' => $odooId,
                'car_listing_id' => $carListing->id,
                'reason' => $reason
            ]);
            
            return $this->successResponse([
                'car_listing' => $carListing,
                'edit' => $edit
            ], 'Car listing edit rejected successfully');
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse(
                "Car listing edit with Odoo ID '{$odooId}' not found",
                404
            );
        } catch (\Exception $e) {
            Log::error('Failed to reject car listing edit: ' . $e->getMessage(), [
                'odoo_id' => $odooId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to reject car listing edit: ' . $e->getMessage(),
                500
            );
        }
    }
}
