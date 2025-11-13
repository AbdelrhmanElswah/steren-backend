<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\GetAuthorizedAgenciesRequest;
use App\Http\Requests\Api\Odoo\StoreAuthorizedAgencyRequest;
use App\Http\Requests\Api\Odoo\UpdateAuthorizedAgencyRequest;
use App\Http\Resources\Api\Odoo\AuthorizedAgencyResource;
use App\Models\AuthorizedAgency;
use App\Services\Odoo\AuthorizedAgencyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AuthorizedAgencyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuthorizedAgencyService $authorizedAgencyService
    ) {}

    public function store(StoreAuthorizedAgencyRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (empty($data['odoo_id'])) {
                $data['odoo_id'] = 'temp_' . uniqid();
            }

            $agency = $this->authorizedAgencyService->create($data);
            $agency->load('media');

            return $this->createdResponse(
                new AuthorizedAgencyResource($agency),
                'Authorized agency created successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyController@store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Failed to create authorized agency: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateAuthorizedAgencyRequest $request, string $odooId): JsonResponse
    {
        try {
            $data = $request->validated();

            if (!empty($data['odoo_id']) && $data['odoo_id'] !== $odooId) {
                $agency = AuthorizedAgency::where('odoo_id', $odooId)->firstOrFail();
                $agency->update(['odoo_id' => $data['odoo_id']]);
                unset($data['odoo_id']);

                if (!empty($data)) {
                    $agency = $this->authorizedAgencyService->update($agency->id, $data);
                }
            } else {
                $agency = $this->authorizedAgencyService->updateByOdooId($odooId, $data);
            }

            $agency->load('media');

            return $this->successResponse(
                new AuthorizedAgencyResource($agency),
                'Authorized agency updated successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyController@updateByOdooId error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Failed to update authorized agency: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->authorizedAgencyService->deleteByOdooId($odooId);

            return $this->noContentResponse('Authorized agency deleted successfully');
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyController@deleteByOdooId error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
            ]);

            return $this->errorResponse('Failed to delete authorized agency: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $agency = $this->authorizedAgencyService->restoreByOdooId($odooId);
            $agency->load('media');

            return $this->successResponse(
                new AuthorizedAgencyResource($agency),
                'Authorized agency restored successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyController@restoreByOdooId error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
            ]);

            return $this->errorResponse('Failed to restore authorized agency: ' . $e->getMessage(), 500);
        }
    }

    public function getAuthorizedAgencies(GetAuthorizedAgenciesRequest $request): JsonResponse
    {
        try {
            $query = AuthorizedAgency::with('media')
                ->when($request->filled('search'), function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('id'), function ($q) use ($request) {
                    $q->where('id', $request->id);
                })
                ->when($request->filled('odoo_id'), function ($q) use ($request) {
                    $q->where('odoo_id', $request->odoo_id);
                })
                ->when($request->filled('partner_id'), function ($q) use ($request) {
                    $q->where('partner_id', $request->partner_id);
                })
                ->orderBy('name');

            if ($request->filled('per_page')) {
                $paginated = $query->paginate(
                    perPage: $request->integer('per_page'),
                    page: $request->integer('page', 1)
                );

                $paginated->getCollection()->load('media');

                $resource = AuthorizedAgencyResource::collection($paginated);
                $resourceData = $resource->response()->getData(true);

                return response()->json([
                    'success' => true,
                    'message' => 'Authorized agencies retrieved successfully',
                    'data' => $resourceData['data'] ?? [],
                    'pagination' => [
                        'current_page' => $resourceData['meta']['current_page'] ?? $paginated->currentPage(),
                        'per_page' => $resourceData['meta']['per_page'] ?? $paginated->perPage(),
                        'last_page' => $resourceData['meta']['last_page'] ?? $paginated->lastPage(),
                        'total' => $resourceData['meta']['total'] ?? $paginated->total(),
                    ],
                    'total' => $paginated->total(),
                ]);
            }

            $agencies = $query->get();

            $resource = AuthorizedAgencyResource::collection($agencies);
            $resourceData = $resource->response()->getData(true);

            return response()->json([
                'success' => true,
                'message' => 'Authorized agencies retrieved successfully',
                'data' => $resourceData['data'] ?? [],
                'total' => $agencies->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyController@getAuthorizedAgencies error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all(),
            ]);

            return $this->errorResponse('Failed to fetch authorized agencies: ' . $e->getMessage(), 500);
        }
    }
}

