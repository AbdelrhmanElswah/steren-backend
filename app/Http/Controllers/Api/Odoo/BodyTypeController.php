<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreBodyTypeRequest;
use App\Http\Requests\Api\Odoo\UpdateBodyTypeRequest;
use App\Http\Requests\Api\Odoo\GetBodyTypesRequest;
use App\Http\Resources\Api\Odoo\BodyTypeResource;
use App\Services\Odoo\BodyTypeService;
use App\Traits\ApiResponse;
use App\Traits\OdooFetchTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BodyTypeController extends Controller
{
    use ApiResponse, OdooFetchTrait;

    public function __construct(
        private BodyTypeService $bodyTypeService
    ) {}

    public function store(StoreBodyTypeRequest $request): JsonResponse
    {
        try {
            $bodyType = $this->bodyTypeService->create($request->validated());

            return $this->createdResponse(
                new BodyTypeResource($bodyType),
                'Body type created successfully'
            );
        } catch (\Exception $e) {
            Log::error('BodyTypeController: Error creating body type', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create body type: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateBodyTypeRequest $request, string $odooId): JsonResponse
    {
        try {
            $bodyType = $this->bodyTypeService->updateByOdooId($odooId, $request->validated());

            return $this->successResponse(
                new BodyTypeResource($bodyType),
                'Body type updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('BodyTypeController: Error updating body type', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update body type: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->bodyTypeService->deleteByOdooId($odooId);

            return $this->noContentResponse('Body type deleted successfully');
        } catch (\Exception $e) {
            Log::error('BodyTypeController: Error deleting body type', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to delete body type: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $bodyType = $this->bodyTypeService->restoreByOdooId($odooId);

            return $this->successResponse(
                new BodyTypeResource($bodyType),
                'Body type restored successfully'
            );
        } catch (\Exception $e) {
            Log::error('BodyTypeController: Error restoring body type', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to restore body type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch body types from regular database for user end
     */
    public function getBodyTypes(GetBodyTypesRequest $request): JsonResponse
    {
        try {
            $bodyTypes = \App\Models\BodyType::with('media')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('id'), function ($query) use ($request) {
                    $query->where('id', $request->id);
                })
                ->when($request->filled('odoo_id'), function ($query) use ($request) {
                    $query->where('odoo_id', $request->odoo_id);
                })
                ->orderBy('name')
                ->get();

            $collection = BodyTypeResource::collection($bodyTypes);
            return $collection->additional([
                'success' => true,
                'message' => 'Body types retrieved successfully',
                'total' => $bodyTypes->count()
            ])->response();

        } catch (\Exception $e) {
            Log::error('BodyTypeController: Error fetching body types', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to fetch body types: ' . $e->getMessage(), 500);
        }
    }
}