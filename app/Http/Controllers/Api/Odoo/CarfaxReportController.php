<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarfaxReportRequest;
use App\Http\Requests\Api\Odoo\UpdateCarfaxReportRequest;
use App\Http\Resources\Api\Odoo\CarfaxReportResource;
use App\Services\Odoo\CarfaxReportService;
use App\Traits\ApiResponse;

class CarfaxReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarfaxReportService $CarfaxReportService
    ) {}

    public function store(StoreCarfaxReportRequest $request)
    {
        $CarfaxReport = $this->CarfaxReportService->create($request->validated());

        return $this->createdResponse(
            new CarfaxReportResource($CarfaxReport),
            'CarfaxReport created successfully'
        );
    }public function updateByOdooId(UpdateCarfaxReportRequest $request, string $odooId)
    {
        $CarfaxReport = $this->CarfaxReportService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CarfaxReportResource($CarfaxReport),
            'CarfaxReport updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->CarfaxReportService->deleteByOdooId($odooId);

        return $this->noContentResponse('CarfaxReport deleted successfully');
    }public function restoreByOdooId(string $odooId)
    {
        $CarfaxReport = $this->CarfaxReportService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarfaxReportResource($CarfaxReport),
            'CarfaxReport restored successfully'
        );
    }
}