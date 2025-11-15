<?php

namespace App\Services;

use App\Models\User;
use App\Services\OdooService;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooPartnerCreationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientService
{
    public function __construct(
        private OdooService $odooService
    ) {}

    public function createClient(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            try {
                // Odoo client creation is REQUIRED - throw exception if it fails
                $odooResult = $this->odooService->createClient($payload);
                
                if (!$odooResult['success']) {
                    Log::error('Odoo client creation failed - no success flag', [
                        'payload' => $payload,
                        'result' => $odooResult
                    ]);
                    throw new OdooPartnerCreationException('Failed to create client in Odoo: ' . ($odooResult['message'] ?? 'Unknown error'));
                }

                $odooClientId = $odooResult['odoo_client_id'];
                $odooClientData = $odooResult['client_data'];
                
                if (!$odooClientId) {
                    Log::error('Odoo client creation succeeded but no client ID returned', [
                        'payload' => $payload,
                        'result' => $odooResult
                    ]);
                    throw new OdooPartnerCreationException('Odoo client created but no client ID returned');
                }

                Log::info('Client created successfully in Odoo', [
                    'odoo_client_id' => $odooClientId,
                    'phone' => $payload['phone']
                ]);

                $partnerId = (string) $odooClientId;

                $user = User::updateOrCreate(
                    ['phone' => $payload['phone']],
                    [
                        'full_name' => $payload['full_name'],
                        'email' => $payload['email'],
                        'phone' => $payload['phone'],
                        'partner_id' => $partnerId,
                        'odoo_id' => (string) $odooClientId,
                        'phone_verified_at' => now(),
                    ]
                );

                Log::info('Client created successfully in both systems', [
                    'user_id' => $user->id,
                    'partner_id' => $partnerId,
                    'odoo_client_id' => $odooClientId,
                    'phone' => $payload['phone']
                ]);

                return [
                    'success' => true,
                    'partner_id' => $partnerId,
                    'odoo_client_id' => $odooClientId,
                    'odoo_success' => true,
                    'user' => $user,
                    'odoo_client_data' => $odooClientData,
                    'message' => 'Client created successfully in both systems'
                ];

            } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                Log::error('Odoo client creation failed - throwing exception', [
                    'payload' => $payload,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getErrorCode() ?? 'UNKNOWN'
                ]);
                // Re-throw to prevent user creation
                throw $e;
            } catch (\Exception $e) {
                Log::error('Failed to create client', [
                    'payload' => $payload,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                throw $e;
            }
        });
    }

    public function getClientByPartnerId(string $partnerId): ?User
    {
        return User::where('partner_id', $partnerId)->first();
    }

    public function updateClient(string $partnerId, array $payload): array
    {
        $user = $this->getClientByPartnerId($partnerId);

        if (!$user) {
            throw new \Exception('Client not found');
        }

        $user->update([
            'full_name' => $payload['full_name'] ?? $user->full_name,
            'email' => $payload['email'] ?? $user->email,
            'phone' => $payload['phone'] ?? $user->phone,
        ]);

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Client updated successfully'
        ];
    }
}
