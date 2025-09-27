<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreLocationRequest;
use App\Http\Requests\Api\Odoo\UpdateLocationRequest;
use App\Http\Resources\Api\Odoo\LocationResource;
use App\Services\Odoo\LocationService;
use App\Traits\ApiResponse;

class LocationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LocationService $LocationService
    ) {}

    public function store(StoreLocationRequest $request)
    {
        $Location = $this->LocationService->create($request->validated());

        return $this->createdResponse(
            new LocationResource($Location),
            'Location created successfully'
        );
    }public function updateByOdooId(UpdateLocationRequest $request, string $odooId)
    {
        $Location = $this->LocationService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new LocationResource($Location),
            'Location updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->LocationService->deleteByOdooId($odooId);

        return $this->noContentResponse('Location deleted successfully');
    }public function restoreByOdooId(string $odooId)
    {
        $Location = $this->LocationService->restoreByOdooId($odooId);

        return $this->successResponse(
            new LocationResource($Location),
            'Location restored successfully'
        );
    }
}