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

    public function createListing(array $listingData): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/listing/create', $listingData);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Listing created in Odoo', [
                    'partner_id' => $listingData['partner_id'] ?? null,
                    'response' => $data
                ]);

                $result = $data['result'] ?? $data;
                
                Log::info('Parsing Odoo response', [
                    'result' => $result,
                    'success_field' => $result['success'] ?? 'not_set',
                    'status_field' => $result['status'] ?? 'not_set'
                ]);
                
                $isSuccess = (isset($result['success']) && $result['success'] === true) || 
                            (isset($result['status']) && $result['status'] == 200);
                
                Log::info('Success check result', [
                    'isSuccess' => $isSuccess,
                    'success_value' => $result['success'] ?? 'not_set',
                    'status_value' => $result['status'] ?? 'not_set'
                ]);
                
                if ($isSuccess) {
                    $listingId = $result['data']['listing_id'] ?? $result['listing_id'] ?? $result['id'] ?? null;
                    
                    Log::info('Extracted Odoo listing ID', [
                        'listing_id' => $listingId,
                        'result_data' => $result['data'] ?? null,
                        'full_result' => $result
                    ]);
                    
                    return [
                        'success' => true,
                        'odoo_listing_id' => $listingId,
                        'message' => $result['message'] ?? 'Listing created successfully',
                        'data' => $result['data'] ?? $result
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Failed to create listing in Odoo'
                ];
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            $errorResponse = $response->json();
            $errorMessage = $errorResponse['error'] ?? $errorResponse['message'] ?? 'Unknown Odoo API error';

            Log::error('Odoo listing creation failed', [
                'listing_data' => $listingData,
                'error' => $errorMessage,
                'status' => $response->status(),
                'response' => $errorResponse
            ]);

            return [
                'success' => false,
                'message' => $errorMessage
            ];

        } catch (OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo listing creation failed', [
                'listing_data' => $listingData,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to create listing in Odoo: ' . $e->getMessage(), 0, $e);
        }
    }

    public function approveListing(string $odooListingId, int $listingId): array
    {
        try {
            $payload = [
                'odoo_listing_id' => $odooListingId,
                'listing_id' => $listingId,
                'action' => 'approve',
                'status' => 'approved',
                'approved_at' => now()->toISOString()
            ];

            Log::info('Sending listing approval to Odoo', [
                'url' => $this->baseUrl . '/api/listing/approve',
                'payload' => $payload
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/listing/approve', $payload);

            Log::info('Odoo listing approval response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Listing approved in Odoo', [
                    'odoo_listing_id' => $odooListingId,
                    'listing_id' => $listingId
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Listing approved successfully'
                ];
            }

            $errorResponse = $response->json();
            Log::error('Odoo listing approval failed', [
                'odoo_listing_id' => $odooListingId,
                'status' => $response->status(),
                'error' => $errorResponse,
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'message' => $errorResponse['message'] ?? 'Failed to approve listing in Odoo'
            ];

        } catch (Exception $e) {
            Log::error('Odoo listing approval failed', [
                'odoo_listing_id' => $odooListingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to approve listing in Odoo: ' . $e->getMessage()
            ];
        }
    }

    public function rejectListing(string $odooListingId, int $listingId, string $reason): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/listing/reject', [
                'odoo_listing_id' => $odooListingId,
                'listing_id' => $listingId,
                'action' => 'reject',
                'status' => 'rejected',
                'rejected_at' => now()->toISOString(),
                'rejection_reason' => $reason
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Listing rejected in Odoo', [
                    'odoo_listing_id' => $odooListingId,
                    'listing_id' => $listingId,
                    'reason' => $reason
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Listing rejected successfully'
                ];
            }

            $errorResponse = $response->json();
            Log::error('Odoo listing rejection failed', [
                'odoo_listing_id' => $odooListingId,
                'error' => $errorResponse
            ]);

            return [
                'success' => false,
                'message' => $errorResponse['message'] ?? 'Failed to reject listing in Odoo'
            ];

        } catch (Exception $e) {
            Log::error('Odoo listing rejection failed', [
                'odoo_listing_id' => $odooListingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reject listing in Odoo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update listing status in Odoo
     * Uses the new endpoint structure: /api/listing/{odoo_listing_id}/{action}
     * 
     * @param string $odooListingId The odoo_listing_id from car_listings table
     * @param int $listingId The local listing ID for reference
     * @param string $action The action to perform (pause, unpause, boost, archive, mark_sold)
     * @param string $status The new status value
     * @return array
     */
    public function updateListingStatus(string $odooListingId, int $listingId, string $action, string $status): array
    {
        try {
            // Map action to endpoint
            $endpoint = match($action) {
                'pause' => 'pause',
                'unpause' => 'unpause',
                'boost' => 'boost',
                'archive' => 'archive',
                'mark_sold' => 'mark_sold',
                default => throw new Exception("Invalid action: {$action}")
            };

            $url = $this->baseUrl . '/api/listing/' . $odooListingId . '/' . $endpoint;

            // Prepare payload with listing_id for reference
            $payload = [
                'listing_id' => $listingId,
                'status' => $status,
                'updated_at' => now()->toISOString()
            ];

            // Add specific data based on action
            switch ($action) {
                case 'pause':
                    $payload['paused_at'] = now()->toISOString();
                    break;
                case 'unpause':
                    $payload['paused_at'] = null;
                    break;
                case 'boost':
                    $payload['is_promoted'] = true;
                    $payload['promotion_started_at'] = now()->toISOString();
                    $payload['promotion_expires_at'] = now()->addDays(7)->toISOString();
                    break;
                case 'archive':
                    $payload['archived_at'] = now()->toISOString();
                    break;
                case 'mark_sold':
                    $payload['sold_at'] = now()->toISOString();
                    break;
            }

            Log::info('Sending listing status update to Odoo', [
                'url' => $url,
                'action' => $action,
                'odoo_listing_id' => $odooListingId,
                'listing_id' => $listingId
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($url, $payload);

            Log::info('Odoo listing status update response', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Listing status updated in Odoo', [
                    'odoo_listing_id' => $odooListingId,
                    'listing_id' => $listingId,
                    'action' => $action,
                    'status' => $status
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Listing status updated successfully'
                ];
            }

            $errorResponse = $response->json();
            Log::error('Odoo listing status update failed', [
                'odoo_listing_id' => $odooListingId,
                'listing_id' => $listingId,
                'action' => $action,
                'status' => $response->status(),
                'error' => $errorResponse,
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'message' => $errorResponse['message'] ?? 'Failed to update listing status in Odoo'
            ];

        } catch (Exception $e) {
            Log::error('Odoo listing status update failed', [
                'odoo_listing_id' => $odooListingId,
                'listing_id' => $listingId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update listing status in Odoo: ' . $e->getMessage()
            ];
        }
    }
}
