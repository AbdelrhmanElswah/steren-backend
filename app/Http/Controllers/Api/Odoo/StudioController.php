<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreStudioRequest;
use App\Http\Requests\Api\Odoo\UpdateStudioRequest;
use App\Http\Resources\Api\Odoo\StudioResource;
use App\Services\Odoo\StudioService;
use App\Traits\ApiResponse;

class StudioController extends Controller
{
    use ApiResponse;

    public function __construct(
        private StudioService $StudioService
    ) {}

    public function store(StoreStudioRequest $request)
    {
        $Studio = $this->StudioService->create($request->validated());

        return $this->createdResponse(
            new StudioResource($Studio),
            'Studio created successfully'
        );
    }public function updateByOdooId(UpdateStudioRequest $request, string $odooId)
    {
        $Studio = $this->StudioService->updateByOdooId($odooId, $request->validated());

        return $this->successResponse(
            new StudioResource($Studio),
            'Studio updated successfully'
        );
    }public function deleteByOdooId(string $odooId)
    {
        $this->StudioService->deleteByOdooId($odooId);

        return $this->noContentResponse('Studio deleted successfully');
    }    public function restoreByOdooId(string $odooId)
    {
        $Studio = $this->StudioService->restoreByOdooId($odooId);

        return $this->successResponse(
            new StudioResource($Studio),
            'Studio restored successfully'
        );
    }
}