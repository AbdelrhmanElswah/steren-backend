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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CarModelController extends Controller
{
    use ApiResponse, OdooFetchTrait;

    public function __construct(
        private CarModelService $CarModelService
    ) {}

    public function store(StoreCarModelRequest $request)
    {
        $CarModel = $this->CarModelService->create($request->validated());

        return $this->createdResponse(
            new CarModelResource($CarModel),
            'CarModel created successfully'
        );
    }public function updateByOdooId(UpdateCarModelRequest $request, string $odooId)
    {
        $CarModel = $this->CarModelService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CarModelResource($CarModel),
            'CarModel updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->CarModelService->deleteByOdooId($odooId);

        return $this->noContentResponse('CarModel deleted successfully');
    }

    public function restoreByOdooId(string $odooId)
    {
        $CarModel = $this->CarModelService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarModelResource($CarModel),
            'CarModel restored successfully'
        );
    }

    public function getCarModels(GetCarModelsRequest $request): JsonResponse
    {
        try {
            $brandsWithModels = $this->CarModelService->getBrandsWithModels($request->validated());
            
            $formattedBrands = $brandsWithModels->map(function ($brand) use ($request) {
                $brandResource = new CarBrandResource($brand);
                $brandData = $brandResource->toArray($request);
                
                $brandData['models'] = $brand->carModels->map(function ($model) {
                    return [
                        'id' => $model->id,
                        'odoo_id' => $model->odoo_id,
                        'name' => $model->name,
                        'car_brand_id' => $model->car_brand_id,
                        'created_at' => $model->created_at?->toISOString(),
                        'updated_at' => $model->updated_at?->toISOString(),
                        'deleted_at' => $model->deleted_at?->toISOString(),
                    ];
                });
                
                return $brandData;
            });

            return response()->json([
                'success' => true,
                'message' => 'Car models retrieved successfully',
                'data' => $formattedBrands,
                'total' => $formattedBrands->count()
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