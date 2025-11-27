<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCityRequest;
use App\Http\Requests\Api\Odoo\UpdateCityRequest;
use App\Http\Requests\Api\Odoo\GetCitiesRequest;
use App\Http\Resources\Api\Odoo\CityResource;
use App\Services\Odoo\CityService;
use App\Services\ErrorTrackingService;
use App\Traits\ApiResponse;
use App\Traits\OdooFetchTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CityController extends Controller
{
    use ApiResponse, OdooFetchTrait;

    public function __construct(
        private CityService $cityService,
        private ErrorTrackingService $errorTracker
    ) {}

    public function store(StoreCityRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $city = $this->cityService->create($request->validated());

            $this->errorTracker->trackApiCall(
                'POST /api/odoo/cities',
                'POST',
                $request->validated(),
                ['success' => true, 'city_id' => $city->id],
                microtime(true) - $startTime
            );

            return $this->createdResponse(
                new CityResource($city),
                'City created successfully'
            );
        } catch (\Exception $e) {
            $this->errorTracker->trackError($e, $request, [
                'operation' => 'store',
                'controller' => 'CityController',
                'validated_data' => $request->validated()
            ]);
            
            Log::error('CityController: Error creating city', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create city: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateCityRequest $request, string $odooId): JsonResponse
    {
        try {
            $city = $this->cityService->updateByOdooId($odooId, $request->validated());

            return $this->successResponse(
                new CityResource($city),
                'City updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('CityController: Error updating city', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update city: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->cityService->deleteByOdooId($odooId);

            return $this->noContentResponse('City deleted successfully');
        } catch (\Exception $e) {
            Log::error('CityController: Error deleting city', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to delete city: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $city = $this->cityService->restoreByOdooId($odooId);

            return $this->successResponse(
                new CityResource($city),
                'City restored successfully'
            );
        } catch (\Exception $e) {
            Log::error('CityController: Error restoring city', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to restore city: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch cities from regular database for user end
     */
    public function getCities(GetCitiesRequest $request): JsonResponse
    {
        try {
            // Build cache key based on request parameters
            $cacheKey = 'odoo_cities_' . md5(serialize($request->only(['search', 'id', 'odoo_id', 'per_page', 'page'])));
            
            // Cache for 24 hours (86400 seconds)
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($request) {
                $cities = \App\Models\City::when($request->filled('search'), function ($query) use ($request) {
                        $query->where('name', 'like', '%' . $request->search . '%');
                    })
                    ->when($request->filled('id'), function ($query) use ($request) {
                        $query->where('id', $request->id);
                    })
                    ->when($request->filled('odoo_id'), function ($query) use ($request) {
                        $query->where('odoo_id', $request->odoo_id);
                    })
                    ->orderBy('name')
                    ->get();

                $collection = CityResource::collection($cities);
                return [
                    'data' => $collection->resolve(),
                    'total' => $cities->count()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Cities retrieved successfully',
                'data' => $result['data'],
                'total' => $result['total'],
            ]);

        } catch (\Exception $e) {
            Log::error('CityController: Error fetching cities', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to fetch cities: ' . $e->getMessage(), 500);
        }
    }
}
