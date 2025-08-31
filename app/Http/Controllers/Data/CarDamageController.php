<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarDamageResource;
use App\Http\Resources\CarPartResource;
use App\Models\CarDamage;
use App\Models\CarListing;
use App\Models\CarPart;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarDamageController extends Controller
{
    use ApiResponse;

    /**
     * Get car damage assessment for a specific car listing
     */
    public function getCarDamage(Request $request, $carListingId): JsonResponse
    {
        try {
            $carListing = CarListing::with(['carDamages.carPart'])->findOrFail($carListingId);
            
            $damages = CarDamageResource::collection($carListing->carDamages);
            $overallStatus = $this->getOverallDamageStatus($carListing->carDamages);

            $data = [
                'car_listing_id' => $carListing->id,
                'car_title' => $carListing->full_title,
                'overall_status' => $overallStatus,
                'damages' => $damages,
                'summary' => [
                    'total_parts' => $carListing->carDamages->count(),
                    'clean_parts' => $carListing->carDamages->where('status', 'clean')->count(),
                    'repaired_parts' => $carListing->carDamages->where('status', 'repaired')->count(),
                    'damaged_parts' => $carListing->carDamages->where('status', 'damaged')->count(),
                    'needs_repair_parts' => $carListing->carDamages->where('status', 'needs_repair')->count(),
                    'total_repair_cost' => $carListing->carDamages->sum('repair_cost'),
                ]
            ];

            return $this->successResponse($data, 'Car damage assessment retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car damage assessment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all car parts for damage assessment
     */
    public function getCarParts(): AnonymousResourceCollection
    {
        try {
            $parts = CarPart::all();
            return CarPartResource::collection($parts);
        } catch (\Exception $e) {
            return CarPartResource::collection([])->additional([
                'success' => false,
                'message' => 'Failed to retrieve car parts: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get damage statistics for a car listing
     */
    public function getDamageStatistics($carListingId): JsonResponse
    {
        try {
            $carListing = CarListing::with('carDamages')->findOrFail($carListingId);
            
            $statistics = [
                'total_parts' => $carListing->carDamages->count(),
                'clean_parts' => $carListing->carDamages->where('status', 'clean')->count(),
                'repaired_parts' => $carListing->carDamages->where('status', 'repaired')->count(),
                'damaged_parts' => $carListing->carDamages->where('status', 'damaged')->count(),
                'needs_repair_parts' => $carListing->carDamages->where('status', 'needs_repair')->count(),
                'total_repair_cost' => $carListing->carDamages->sum('repair_cost'),
                'verified_damages' => $carListing->carDamages->where('is_verified', true)->count(),
                'overall_status' => $this->getOverallDamageStatus($carListing->carDamages),
            ];

            return $this->successResponse($statistics, 'Damage statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve damage statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get overall damage status for a car
     */
    private function getOverallDamageStatus($damages): string
    {
        if ($damages->where('status', 'damaged')->count() > 0) {
            return 'damaged';
        }
        
        if ($damages->where('status', 'needs_repair')->count() > 0) {
            return 'needs_repair';
        }
        
        if ($damages->where('status', 'repaired')->count() > 0) {
            return 'repaired';
        }
        
        return 'clean';
    }
}
