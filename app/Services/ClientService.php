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
                $odooClientId = null;
                $odooClientData = null;
                $odooSuccess = false;

                try {
                    $odooResult = $this->odooService->createClient($payload);
                    
                    if ($odooResult['success']) {
                        $odooClientId = $odooResult['odoo_client_id'];
                        $odooClientData = $odooResult['client_data'];
                        $odooSuccess = true;
                        
                        Log::info('Client created successfully in Odoo', [
                            'odoo_client_id' => $odooClientId,
                            'phone' => $payload['phone']
                        ]);
                    }
                } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                    Log::warning('Odoo client creation failed, continuing with local creation', [
                        'payload' => $payload,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getErrorCode() ?? 'UNKNOWN'
                    ]);
                }

                $partnerId = $odooSuccess ? (string) $odooClientId : $this->generatePartnerId();

                $user = User::updateOrCreate(
                    ['phone' => $payload['phone']],
                    [
                        'full_name' => $payload['full_name'],
                        'email' => $payload['email'],
                        'phone' => $payload['phone'],
                        'partner_id' => $partnerId,
                        'odoo_id' => $odooClientId ? (string) $odooClientId : null,
                        'phone_verified_at' => now(),
                    ]
                );

                Log::info('Client created successfully', [
                    'user_id' => $user->id,
                    'partner_id' => $partnerId,
                    'odoo_client_id' => $odooClientId,
                    'odoo_success' => $odooSuccess,
                    'phone' => $payload['phone']
                ]);

                $message = $odooSuccess 
                    ? 'Client created successfully in both systems'
                    : 'Client created successfully (Odoo integration unavailable)';

                return [
                    'success' => true,
                    'partner_id' => $partnerId,
                    'odoo_client_id' => $odooClientId,
                    'odoo_success' => $odooSuccess,
                    'user' => $user,
                    'odoo_client_data' => $odooClientData,
                    'message' => $message
                ];

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

    private function generatePartnerId(): string
    {
        do {
            $partnerId = 'PARTNER_' . strtoupper(Str::random(8));
        } while (User::where('partner_id', $partnerId)->exists());

        return $partnerId;
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
