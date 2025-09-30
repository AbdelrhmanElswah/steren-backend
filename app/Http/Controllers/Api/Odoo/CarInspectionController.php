<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarInspectionRequest;
use App\Http\Requests\Api\Odoo\UpdateCarInspectionRequest;
use App\Http\Resources\Api\Odoo\CarInspectionResource;
use App\Services\Odoo\CarInspectionService;
use App\Traits\ApiResponse;

class CarInspectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarInspectionService $CarInspectionService
    ) {}

    public function store(StoreCarInspectionRequest $request)
    {
        $CarInspection = $this->CarInspectionService->create($request->validated());

        return $this->createdResponse(
            new CarInspectionResource($CarInspection),
            'CarInspection created successfully'
        );
    }

    public function updateByOdooId(UpdateCarInspectionRequest $request, string $odooId)
    {
        $CarInspection = $this->CarInspectionService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CarInspectionResource($CarInspection),
            'CarInspection updated successfully'
        );
    }

    public function deleteByOdooId(string $odooId)
    {
        $this->CarInspectionService->deleteByOdooId($odooId);

        return $this->noContentResponse('CarInspection deleted successfully');
    }

    public function restoreByOdooId(string $odooId)
    {
        $CarInspection = $this->CarInspectionService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarInspectionResource($CarInspection),
            'CarInspection restored successfully'
        );
    }
}