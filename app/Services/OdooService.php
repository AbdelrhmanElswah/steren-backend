<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooPartnerCreationException;
use App\Exceptions\OdooOtpVerificationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OdooService
{
    protected $baseUrl;
    protected $database;
    protected $username;
    protected $password;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.odoo.base_url');
        $this->database = config('services.odoo.database');
        $this->username = config('services.odoo.username');
        $this->password = config('services.odoo.password');
        $this->apiKey = config('services.odoo.api_key');
    }

    public function createPartnerAfterOtpVerification(User $user, string $otpCode): array
    {
        try {
            $this->verifyOtpWithOdoo($user->phone, $otpCode);

            $partnerData = $this->preparePartnerData($user);
            $partnerId = $this->createPartner($partnerData);

            $user->update(['partner_id' => $partnerId]);
            
            return [
                'success' => true,
                'message' => 'Partner created successfully',
                'partner_id' => $partnerId,
                'user' => $user->fresh()
            ];

        } catch (OdooOtpVerificationException|OdooPartnerCreationException|OdooConnectionException $e) {
            Log::error('Odoo partner creation failed', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode() ?? 'UNKNOWN'
            ]);

            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected error during Odoo partner creation', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('An unexpected error occurred while creating partner: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function verifyOtpWithOdoo(string $phone, string $otpCode): void
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/verify-otp', [
                'phone' => $phone,
                'otp_code' => $otpCode,
                'database' => $this->database
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']) && $data['result']['success']) {
                    return;
                }

                throw new OdooOtpVerificationException($data['result']['message'] ?? 'OTP verification failed');
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            throw new OdooOtpVerificationException('OTP verification failed: Invalid response from Odoo');

        } catch (OdooOtpVerificationException|OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo OTP verification failed', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to verify OTP with Odoo: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function createPartner(array $partnerData): string
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/create-partner', [
                'database' => $this->database,
                'partner_data' => $partnerData
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Handle the JSON-RPC response structure
                if (isset($data['result']) && $data['result']['success']) {
                    $partnerInfo = $data['result']['data'];
                    $partnerId = $partnerInfo['id'] ?? null;
                    
                    if (!$partnerId) {
                        throw new OdooPartnerCreationException('Partner created but no partner ID returned');
                    }
                    
                    return (string) $partnerId;
                }

                throw new OdooPartnerCreationException('Partner creation failed: ' . ($data['result']['message'] ?? 'Unknown error'));
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            $errorData = $response->json();
            $errorMessage = 'Failed to create partner in Odoo';
            
            if (isset($errorData['result']['message'])) {
                $errorMessage = $errorData['result']['message'];
            }
            
            throw new OdooPartnerCreationException($errorMessage);

        } catch (OdooPartnerCreationException|OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo partner creation failed', [
                'partner_data' => $partnerData,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to create partner in Odoo: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function preparePartnerData(User $user): array
    {
        return [
            'full_name' => $user->full_name,
            'phone' => $user->phone,
            'email' => $user->email,
        ];
    }

    public function getPartnerInfo(string $partnerId): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/api/partner/' . $partnerId, [
                'database' => $this->database
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']) && $data['result']['success']) {
                    return [
                        'success' => true,
                        'partner' => $data['result']['data'] ?? null,
                        'message' => $data['result']['message'] ?? 'Partner retrieved successfully'
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['result']['message'] ?? 'Failed to retrieve partner'
                ];
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            return [
                'success' => false,
                'message' => 'Failed to retrieve partner',
                'errors' => ['Invalid response from Odoo']
            ];

        } catch (OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo partner retrieval failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to retrieve partner from Odoo: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updatePartner(string $partnerId, array $updateData): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->put($this->baseUrl . '/api/partner/' . $partnerId, [
                'database' => $this->database,
                'update_data' => $updateData
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Handle the JSON-RPC response structure
                if (isset($data['result']) && $data['result']['success']) {
                    return [
                        'success' => true,
                        'message' => $data['result']['message'] ?? 'Partner updated successfully'
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['result']['message'] ?? 'Failed to update partner'
                ];
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            return [
                'success' => false,
                'message' => 'Failed to update partner',
                'errors' => ['Invalid response from Odoo']
            ];

        } catch (OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo partner update failed', [
                'partner_id' => $partnerId,
                'update_data' => $updateData,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to update partner in Odoo: ' . $e->getMessage(), 0, $e);
        }
    }
    public function createClient(array $clientData): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/client/create', [
                'full_name' => $clientData['full_name'],
                'phone' => $clientData['phone'],
                'email' => $clientData['email'],
                'database' => $this->database
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']) && $data['result']['success']) {
                    $clientInfo = $data['result']['data'];
                    
                    return [
                        'success' => true,
                        'message' => $data['result']['message'] ?? 'Client created successfully',
                        'odoo_client_id' => $clientInfo['id'],
                        'client_data' => $clientInfo
                    ];
                }

                throw new OdooPartnerCreationException('Client creation failed: ' . ($data['result']['message'] ?? 'Unknown error'));
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            $errorData = $response->json();
            $errorMessage = 'Failed to create client in Odoo';
            
            if (isset($errorData['result']['message'])) {
                $errorMessage = $errorData['result']['message'];
            }
            
            throw new OdooPartnerCreationException($errorMessage);

        } catch (OdooPartnerCreationException|OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo client creation failed', [
                'client_data' => $clientData,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to create client in Odoo: ' . $e->getMessage(), 0, $e);
        }
    }
}
