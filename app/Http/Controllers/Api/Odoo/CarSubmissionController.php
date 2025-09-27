<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarSubmissionRequest;
use App\Http\Requests\Api\Odoo\UpdateCarSubmissionRequest;
use App\Http\Resources\Api\Odoo\CarSubmissionResource;
use App\Services\Odoo\CarSubmissionService;
use App\Traits\ApiResponse;

class CarSubmissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarSubmissionService $CarSubmissionService
    ) {}

    public function store(StoreCarSubmissionRequest $request)
    {
        $CarSubmission = $this->CarSubmissionService->create($request->validated());

        return $this->createdResponse(
            new CarSubmissionResource($CarSubmission),
            'CarSubmission created successfully'
        );
    }public function updateByOdooId(UpdateCarSubmissionRequest $request, string $odooId)
    {
        $CarSubmission = $this->CarSubmissionService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new CarSubmissionResource($CarSubmission),
            'CarSubmission updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->CarSubmissionService->deleteByOdooId($odooId);

        return $this->noContentResponse('CarSubmission deleted successfully');
    }public function restoreByOdooId(string $odooId)
    {
        $CarSubmission = $this->CarSubmissionService->restoreByOdooId($odooId);

        return $this->successResponse(
            new CarSubmissionResource($CarSubmission),
            'CarSubmission restored successfully'
        );
    }
}