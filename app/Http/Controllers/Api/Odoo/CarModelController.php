<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarModelRequest;
use App\Http\Requests\Api\Odoo\UpdateCarModelRequest;
use App\Http\Requests\Api\Odoo\GetCarModelsRequest;
use App\Http\Resources\Api\Odoo\CarModelResource;
use App\Http\Resources\Api\Odoo\CarBrandResource;
use App\Services\Odoo\CarModelService;
use App\Traits\ApiResponse;
use App\Traits\OdooFetchTrait;
use App\Traits\LocalizedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CarModelController extends Controller
{
    use ApiResponse, OdooFetchTrait, LocalizedResponse;

    public function __construct(
        private CarModelService $CarModelService
    ) {}

    public function store(StoreCarModelRequest $request): JsonResponse
    {
        try {
            $carModel = $this->CarModelService->create($request->validated());

            return $this->createdResponse(
                new CarModelResource($carModel),
                'Car model created successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarModelController: Error creating car model', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create car model: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateCarModelRequest $request, string $odooId): JsonResponse
    {
        try {
            $carModel = $this->CarModelService->updateByOdooId($odooId, $request->validated());

            return $this->successResponse(
                new CarModelResource($carModel),
                'Car model updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarModelController: Error updating car model', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update car model: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->CarModelService->deleteByOdooId($odooId);

            return $this->noContentResponse('Car model deleted successfully');
        } catch (\Exception $e) {
            Log::error('CarModelController: Error deleting car model', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to delete car model: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $carModel = $this->CarModelService->restoreByOdooId($odooId);

            return $this->successResponse(
                new CarModelResource($carModel),
                'Car model restored successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarModelController: Error restoring car model', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to restore car model: ' . $e->getMessage(), 500);
        }
    }

    public function getCarModels(GetCarModelsRequest $request): JsonResponse
    {
        try {
            // Get language from request
            $lang = $this->getLanguageFromRequest($request);
            
            // Build cache key based on request parameters including language
            $cacheKey = 'odoo_car_models_' . md5(serialize($request->only(['search', 'id', 'odoo_id', 'brand_id', 'lang'])));
            
            // Cache for 24 hours (86400 seconds)
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($request, $lang) {
                $brandsWithModels = $this->CarModelService->getBrandsWithModels($request->validated());
                
                // Build response directly without using resources (faster, avoids temporary URL generation)
                $formattedBrands = $brandsWithModels->map(function ($brand) use ($lang) {
                    $mediaLink = null;
                    if ($brand->media && $brand->media->path_file_name) {
                        $mediaLink = $brand->media->getS3PublicUrl($brand->media->path_file_name);
                    }

                    $brandData = [
                        'id' => $brand->id,
                        'odoo_id' => $brand->odoo_id,
                        'name' => $this->getLocalizedField($brand, 'name', $lang),
                        'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                        'models' => $brand->carModels->map(function ($model) use ($lang) {
                            return [
                                'id' => $model->id,
                                'odoo_id' => $model->odoo_id,
                                'name' => $this->getLocalizedField($model, 'name', $lang),
                                'car_brand_id' => $model->car_brand_id,
                            ];
                        })->toArray(),
                    ];
                    
                    return $brandData;
                })->toArray();

                return [
                    'data' => $formattedBrands,
                    'total' => count($formattedBrands),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Car models retrieved successfully',
                'data' => $result['data'],
                'total' => $result['total'],
            ]);

        } catch (\Exception $e) {
            Log::error('CarModelController: Error fetching car models', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->errorResponse('Failed to fetch car models: ' . $e->getMessage(), 500);
        }
    }
}