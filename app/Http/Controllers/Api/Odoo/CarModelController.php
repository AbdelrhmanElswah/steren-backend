<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarModelRequest;
use App\Http\Requests\Api\Odoo\UpdateCarModelRequest;
use App\Http\Resources\Api\Odoo\CarModelResource;
use App\Services\Odoo\CarModelService;
use App\Traits\ApiResponse;

class CarModelController extends Controller
{
    use ApiResponse;

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
    }public function restoreByOdooId(string $odooId)
    {
        $CarModel = $this->CarModelService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarModelResource($CarModel),
            'CarModel restored successfully'
        );
    }
}