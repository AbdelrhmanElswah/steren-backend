<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCityRequest;
use App\Http\Requests\Api\Odoo\UpdateCityRequest;
use App\Http\Resources\Api\Odoo\CityResource;
use App\Services\Odoo\CityService;
use App\Traits\ApiResponse;

class CityController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CityService $cityService
    ) {}

    public function store(StoreCityRequest $request)
    {
        $city = $this->cityService->create($request->validated());

        return $this->createdResponse(
            new CityResource($city),
            'City created successfully'
        );
    }

    public function updateByOdooId(UpdateCityRequest $request, string $odooId)
    {
        $city = $this->cityService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CityResource($city),
            'City updated successfully'
        );
    }

    public function deleteByOdooId(string $odooId)
    {
        $this->cityService->deleteByOdooId($odooId);

        return $this->noContentResponse('City deleted successfully');
    }

    public function restoreByOdooId(string $odooId)
    {
        $city = $this->cityService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CityResource($city),
            'City restored successfully'
        );
    }
}
