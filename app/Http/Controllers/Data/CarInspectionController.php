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
            $carListing = CarListing::with(['inspection.inspector'])->findOrFail($carListingId);
            
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
                'average_score' => CarInspection::whereNotNull('score')->avg('score'),
                'inspections_by_score_range' => [
                    'excellent' => CarInspection::where('score', '>=', 90)->count(),
                    'good' => CarInspection::whereBetween('score', [70, 89])->count(),
                    'fair' => CarInspection::whereBetween('score', [50, 69])->count(),
                    'poor' => CarInspection::where('score', '<', 50)->count(),
                ]
            ];

            return $this->successResponse($statistics, 'Inspection statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve inspection statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get inspections by inspector
     */
    public function getInspectionsByInspector($inspectorId): AnonymousResourceCollection
    {
        try {
            $inspections = CarInspection::where('inspector_id', $inspectorId)
                ->with(['carListing.brand', 'carListing.carModel'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return CarInspectionResource::collection($inspections);
        } catch (\Exception $e) {
            return CarInspectionResource::collection([])->additional([
                'success' => false,
                'message' => 'Failed to retrieve inspections by inspector: ' . $e->getMessage()
            ]);
        }
    }
}
