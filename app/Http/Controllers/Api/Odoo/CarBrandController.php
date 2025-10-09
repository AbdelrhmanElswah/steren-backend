<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarBrandRequest;
use App\Http\Requests\Api\Odoo\UpdateCarBrandRequest;
use App\Http\Requests\Api\Odoo\GetCarBrandsRequest;
use App\Http\Resources\Api\Odoo\CarBrandResource;
use App\Services\Odoo\CarBrandService;
use App\Traits\ApiResponse;
use App\Traits\OdooFetchTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CarBrandController extends Controller
{
    use ApiResponse, OdooFetchTrait;

    public function __construct(
        private CarBrandService $carBrandService
    ) {}

    public function store(StoreCarBrandRequest $request): JsonResponse
    {
        try {
            $carBrand = $this->carBrandService->create($request->validated());
            $carBrand->load('media');

            return $this->createdResponse(
                new CarBrandResource($carBrand),
                'Car brand created successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarBrandController: Error creating car brand', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create car brand: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateCarBrandRequest $request, string $odooId): JsonResponse
    {
        try {
            $carBrand = $this->carBrandService->updateByOdooId($odooId, $request->validated());
            $carBrand->load('media');

            return $this->successResponse(
                new CarBrandResource($carBrand),
                'Car brand updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarBrandController: Error updating car brand', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update car brand: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->carBrandService->deleteByOdooId($odooId);

            return $this->noContentResponse('Car brand deleted successfully');
        } catch (\Exception $e) {
            Log::error('CarBrandController: Error deleting car brand', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to delete car brand: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $carBrand = $this->carBrandService->restoreByOdooId($odooId);

            return $this->successResponse(
                new CarBrandResource($carBrand),
                'Car brand restored successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarBrandController: Error restoring car brand', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to restore car brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch brands from regular database for user end
     */
    public function getBrands(GetCarBrandsRequest $request): JsonResponse
    {
        try {
            $brands = \App\Models\CarBrand::with('media')
                ->when($request->filled('search'), function ($query) use ($request) {
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

            $collection = CarBrandResource::collection($brands);
            return $collection->additional([
                'success' => true,
                'message' => 'Brands retrieved successfully',
                'total' => $brands->count()
            ])->response();

        } catch (\Exception $e) {
            Log::error('CarBrandController: Error fetching brands', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to fetch brands: ' . $e->getMessage(), 500);
        }
    }
}