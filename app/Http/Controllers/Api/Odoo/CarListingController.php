<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarListingRequest;
use App\Http\Requests\Api\Odoo\UpdateCarListingRequest;
use App\Http\Resources\Api\Odoo\CarListingResource;
use App\Services\Odoo\CarListingService;
use App\Traits\ApiResponse;

class CarListingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarListingService $CarListingService
    ) {}

    public function store(StoreCarListingRequest $request)
    {
        $CarListing = $this->CarListingService->create($request->validated());

        return $this->createdResponse(
            new CarListingResource($CarListing),
            'CarListing created successfully'
        );
    }public function updateByOdooId(UpdateCarListingRequest $request, string $odooId)
    {
        $CarListing = $this->CarListingService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CarListingResource($CarListing),
            'CarListing updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->CarListingService->deleteByOdooId($odooId);

        return $this->noContentResponse('CarListing deleted successfully');
    }public function restoreByOdooId(string $odooId)
    {
        $CarListing = $this->CarListingService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarListingResource($CarListing),
            'CarListing restored successfully'
        );
    }
}