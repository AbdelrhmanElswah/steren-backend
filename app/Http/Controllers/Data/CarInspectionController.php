<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarInspectionResource;
use App\Models\CarInspection;
use App\Models\CarListing;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarInspectionController extends Controller
{
    use ApiResponse;

    /**
     * Get car inspection for a specific car listing
     */
    public function getCarInspection($carListingId): JsonResponse
    {
        try {
            $carListing = CarListing::with(['inspection.documents'])->findOrFail($carListingId);
            
            if (!$carListing->inspection) {
                return $this->notFoundResponse('No inspection report found for this car');
            }

            $inspection = new CarInspectionResource($carListing->inspection);
            return $this->resourceResponse($inspection, 'Car inspection retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car inspection: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get inspection statistics
     */
    public function getInspectionStatistics(): JsonResponse
    {
        try {
            $statistics = [
                'total_inspections' => CarInspection::count(),
                'completed_inspections' => CarInspection::completed()->count(),
                'pending_inspections' => CarInspection::where('status', 'pending')->count(),
                'in_progress_inspections' => CarInspection::where('status', 'in_progress')->count(),
                'failed_inspections' => CarInspection::where('status', 'failed')->count(),
                'verified_inspections' => CarInspection::verified()->count(),
                // Inspection pass/fail statistics
                'inspection_results' => [
                    'right_side_pass' => CarInspection::where('right_side', true)->count(),
                    'left_side_pass' => CarInspection::where('left_side', true)->count(),
                    'roof_pass' => CarInspection::where('roof', true)->count(),
                    'front_pass' => CarInspection::where('front', true)->count(),
                    'back_pass' => CarInspection::where('back', true)->count(),
                ]
            ];

            return $this->successResponse($statistics, 'Inspection statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve inspection statistics: ' . $e->getMessage(), 500);
        }
    }
}
