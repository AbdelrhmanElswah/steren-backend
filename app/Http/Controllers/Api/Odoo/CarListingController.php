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

        return $this->createdResponse([
            'car_listing' => new CarListingResource($CarListing),
            'odoo_sync' => [
                'status' => $CarListing->odoo_id ? 'success' : 'pending',
                'odoo_id' => $CarListing->odoo_id,
                'message' => $CarListing->odoo_id ? 'Successfully synced to Odoo' : 'Odoo sync pending or failed'
            ]
        ], 'CarListing created successfully and synced to Odoo');
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