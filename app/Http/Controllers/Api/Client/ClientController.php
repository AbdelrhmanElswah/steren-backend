<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\CreateClientRequest;
use App\Http\Resources\Api\Client\ClientResource;
use App\Services\ClientService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ClientService $clientService
    ) {}
    public function create(CreateClientRequest $request): JsonResponse
    {
        try {
            $result = $this->clientService->createClient($request->validated());

            $resource = new ClientResource($result['user']);
            return $resource->additional([
                'success' => true,
                'message' => $result['message'],
                'partner_id' => $result['partner_id'],
                'odoo_client_id' => $result['odoo_client_id'] ?? null,
                'odoo_success' => $result['odoo_success'] ?? false,
                'odoo_client_data' => $result['odoo_client_data'] ?? null
            ])->response()->setStatusCode(201);

        } catch (\Exception $e) {
            Log::error('ClientController: Error creating client', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create client: ' . $e->getMessage(), 500);
        }
    }

    public function getByPartnerId(string $partnerId): JsonResponse
    {
        try {
            $user = $this->clientService->getClientByPartnerId($partnerId);

            if (!$user) {
                return $this->notFoundResponse('Client not found');
            }

            return $this->successResponse(
                new ClientResource($user),
                'Client retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('ClientController: Error retrieving client', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'partner_id' => $partnerId
            ]);

            return $this->errorResponse('Failed to retrieve client: ' . $e->getMessage(), 500);
        }
    }
}
