<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimilarCarsRequest;
use App\Http\Requests\SimilarCarsByPriceRequest;
use App\Http\Requests\SimilarCarsByYearRequest;
use App\Http\Resources\SimilarCarsResponseResource;
use App\Services\SimilarCarsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimilarCarsController extends Controller
{
    use ApiResponse;

    protected SimilarCarsService $similarCarsService;

    public function __construct(SimilarCarsService $similarCarsService)
    {
        $this->similarCarsService = $similarCarsService;
    }

    /**
     * Get similar cars based on a reference car listing
     */
    public function getSimilarCars(int $carListingId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $limit = $request->get('limit', 8);
            $results = $this->similarCarsService->getSimilarCars($carListingId, $limit);
            
            if (!$results['reference_car']) {
                return $this->errorResponse('Car listing not found', 404);
            }

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'car_listing_id' => $carListingId,
                'request_data' => $request->all()
            ]);

            return $this->errorResponse('Failed to get similar cars: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get similar cars by brand and model
     */
    public function getSimilarCarsByBrandModel(SimilarCarsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            if (empty($validated['brand_id'])) {
                return $this->errorResponse('Brand ID is required', 400);
            }

            $limit = $validated['limit'] ?? 8;
            $results = $this->similarCarsService->getSimilarCarsByBrandModel(
                $validated['brand_id'],
                $validated['model_id'] ?? null,
                $limit
            );

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars by brand/model retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars by brand/model', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to get similar cars by brand/model: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get similar cars by price range
     */
    public function getSimilarCarsByPrice(SimilarCarsByPriceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $limit = $validated['limit'] ?? 8;
            $priceTolerance = $validated['price_tolerance'] ?? 0.2; // 20% default tolerance
            
            $results = $this->similarCarsService->getSimilarCarsByPrice(
                $validated['price'],
                $priceTolerance,
                $limit
            );

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars by price retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars by price', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to get similar cars by price: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get similar cars by year range
     */
    public function getSimilarCarsByYear(SimilarCarsByYearRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $limit = $validated['limit'] ?? 8;
            $yearTolerance = $validated['year_tolerance'] ?? 2; // 2 years default tolerance
            
            $results = $this->similarCarsService->getSimilarCarsByYear(
                $validated['year'],
                $yearTolerance,
                $limit
            );

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars by year retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars by year', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to get similar cars by year: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get similar cars by body type
     */
    public function getSimilarCarsByBodyType(int $bodyTypeId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $limit = $request->get('limit', 8);
            $results = $this->similarCarsService->getSimilarCarsByBodyType($bodyTypeId, $limit);

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars by body type retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars by body type', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'body_type_id' => $bodyTypeId,
                'request_data' => $request->all()
            ]);

            return $this->errorResponse('Failed to get similar cars by body type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get similar cars using advanced criteria
     */
    public function getSimilarCarsAdvanced(SimilarCarsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Remove limit from criteria and use it separately
            $limit = $validated['limit'] ?? 8;
            unset($validated['limit']);
            
            // Check if at least one criteria is provided
            $hasCriteria = !empty(array_filter($validated, function($value) {
                return $value !== null && $value !== '';
            }));
            
            if (!$hasCriteria) {
                return $this->errorResponse('At least one search criteria is required', 400);
            }

            $results = $this->similarCarsService->getSimilarCarsAdvanced($validated, $limit);

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars using advanced criteria retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars with advanced criteria', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to get similar cars with advanced criteria: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get similar cars recommendations for homepage/landing page
     */
    public function getSimilarCarsRecommendations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:20',
                'category' => 'nullable|string|in:featured,new_arrivals,sponsored,trending'
            ]);

            $limit = $request->get('limit', 8);
            $category = $request->get('category', 'featured');

            // Get different types of recommendations based on category
            switch ($category) {
                case 'featured':
                    $results = $this->getFeaturedRecommendations($limit);
                    break;
                case 'new_arrivals':
                    $results = $this->getNewArrivalsRecommendations($limit);
                    break;
                case 'sponsored':
                    $results = $this->getSponsoredRecommendations($limit);
                    break;
                case 'trending':
                    $results = $this->getTrendingRecommendations($limit);
                    break;
                default:
                    $results = $this->getFeaturedRecommendations($limit);
            }

            $data = new SimilarCarsResponseResource($results);

            return $this->successResponse($data, 'Similar cars recommendations retrieved successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error getting similar cars recommendations', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->errorResponse('Failed to get similar cars recommendations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clear similar cars cache (Admin only)
     */
    public function clearSimilarCarsCache(Request $request): JsonResponse
    {
        try {
            // Add authorization check for admin users if needed
            $this->similarCarsService->clearSimilarCarsCache();

            return $this->successResponse([], 'Similar cars cache cleared successfully');
        } catch (\Exception $e) {
            Log::error('SimilarCarsController: Error clearing similar cars cache', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);

            return $this->errorResponse('Failed to clear similar cars cache: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get featured recommendations
     */
    private function getFeaturedRecommendations(int $limit): array
    {
        $criteria = ['brand_id' => null]; // This will be handled by the service to get featured cars
        return $this->similarCarsService->getSimilarCarsAdvanced($criteria, $limit);
    }

    /**
     * Get new arrivals recommendations
     */
    private function getNewArrivalsRecommendations(int $limit): array
    {
        // Get cars from the last 30 days
        $criteria = [
            'year_min' => date('Y') - 5, // Recent years
            'year_max' => date('Y')
        ];
        return $this->similarCarsService->getSimilarCarsAdvanced($criteria, $limit);
    }

    /**
     * Get sponsored recommendations
     */
    private function getSponsoredRecommendations(int $limit): array
    {
        // This would need to be implemented in the service
        // For now, return featured cars
        return $this->getFeaturedRecommendations($limit);
    }

    /**
     * Get trending recommendations
     */
    private function getTrendingRecommendations(int $limit): array
    {
        // Get cars with high view counts or recent activity
        $criteria = [
            'year_min' => date('Y') - 3,
            'year_max' => date('Y')
        ];
        return $this->similarCarsService->getSimilarCarsAdvanced($criteria, $limit);
    }
}
