<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarBrandRequest;
use App\Http\Requests\Api\Odoo\UpdateCarBrandRequest;
use App\Http\Resources\Api\Odoo\CarBrandResource;
use App\Services\Odoo\CarBrandService;
use App\Traits\ApiResponse;

class CarBrandController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarBrandService $carBrandService
    ) {}

    public function store(StoreCarBrandRequest $request)
    {
        $carBrand = $this->carBrandService->create($request->validated());

        return $this->createdResponse(
            new CarBrandResource($carBrand),
            'Car brand created successfully'
        );
    }

    public function updateByOdooId(UpdateCarBrandRequest $request, string $odooId)
    {
        $carBrand = $this->carBrandService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CarBrandResource($carBrand),
            'Car brand updated successfully'
        );
    }

    public function deleteByOdooId(string $odooId)
    {
        $this->carBrandService->deleteByOdooId($odooId);

        return $this->noContentResponse('Car brand deleted successfully');
    }

    public function restoreByOdooId(string $odooId)
    {
        $carBrand = $this->carBrandService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarBrandResource($carBrand),
            'Car brand restored successfully'
        );
    }
}