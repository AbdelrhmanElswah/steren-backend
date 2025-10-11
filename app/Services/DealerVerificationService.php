<?php

namespace App\Services;

use App\Models\User;
use App\Models\DealerApplication;
use App\Models\SubscriptionPlan;
use App\Models\Status;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class DealerVerificationService
{
    protected $baseUrl;
    protected $database;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.odoo.base_url');
        $this->database = config('services.odoo.database');
        $this->apiKey = config('services.odoo.api_key');
    }

    public function submitVerification(array $data, ?User $authenticatedUser = null): array
    {
        try {
            $user = $authenticatedUser ?? User::where('partner_id', $data['partner_id'])->first();
            
            if (!$user) {
                throw new OdooServiceException('User not found for partner ID: ' . ($data['partner_id'] ?? 'N/A'));
            }

            if ($user->is_dealer) {
                throw new OdooServiceException('User is already a verified dealer');
            }
            
            $data['partner_id'] = $user->partner_id;

            DB::beginTransaction();

            $subscriptionPlan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

            $dealerApplication = $this->createDealerApplication($user, $data, $subscriptionPlan);

            $odooResponse = $this->submitToOdoo($data, $dealerApplication);

            if ($odooResponse['success']) {
                $underReviewStatus = Status::findByNameAndType('under_review', 'dealer_application');
                
                $odooId = (string) $data['partner_id'];
                
                $dealerApplication->update([
                    'status_id' => $underReviewStatus?->id,
                    'odoo_id' => $odooId,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Dealer verification submitted successfully',
                    'application_id' => $dealerApplication->id,
                    'odoo_id' => $odooId,
                    'status' => 'under_review'
                ];
            } else {
                throw new OdooServiceException($odooResponse['message'] ?? 'Failed to submit to Odoo');
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Dealer verification submission failed', [
                'partner_id' => $data['partner_id'] ?? null,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw $e;
        }
    }

    protected function createDealerApplication(User $user, array $data, SubscriptionPlan $subscriptionPlan): DealerApplication
    {
            $pendingStatus = Status::findByNameAndType('pending', 'dealer_application');
            
            $applicationData = [
                'user_id' => $user->id,
                'seller_type' => $data['seller_type'],
                'owner_manager_name' => $data['owner_manager_name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email_address'],
                'dealership_company_name' => $data['dealership_name'],
                'vehicles_in_stock' => $data['vehicles_in_stock'] ?? null,
                'years_in_business' => $data['years_in_business'] ?? null,
                'city_address' => $data['city_address'],
                'subscription_plan_id' => $subscriptionPlan->id,
                'status_id' => $pendingStatus?->id,
                'terms_accepted' => true,
                'information_confirmed' => true,
            ];

        if ($data['seller_type'] === 'authorized') {
            $applicationData['automotive_brand_represented'] = $data['automotive_brand'];
            $applicationData['business_license_number'] = $data['business_license_name'] ?? null;
            
            if (isset($data['authorization_letter']) && $data['authorization_letter'] instanceof \Illuminate\Http\UploadedFile) {
                $applicationData['authorization_letter_path'] = $this->storeUploadedFile(
                    $data['authorization_letter'],
                    'dealer-applications/authorization-letters'
                );
            }
            
            if (isset($data['business_license']) && $data['business_license'] instanceof \Illuminate\Http\UploadedFile) {
                $applicationData['business_license_path'] = $this->storeUploadedFile(
                    $data['business_license'],
                    'dealer-applications/business-licenses'
                );
            }
        }

        return DealerApplication::create($applicationData);
    }

    protected function submitToOdoo(array $data, DealerApplication $dealerApplication): array
    {
        try {
            $payload = $this->prepareOdooPayload($data, $dealerApplication);

            $jsonRpcPayload = [
                'jsonrpc' => '2.0',
                'id' => null,
                'method' => 'call',
                'params' => [
                    'service' => 'verification',
                    'method' => 'submit',
                    'args' => [$payload]
                ]
            ];

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/api/verification/submit', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info('Odoo API Response', [
                    'response_data' => $responseData,
                    'partner_id' => $data['partner_id'] ?? null,
                    'application_id' => $dealerApplication->id ?? null
                ]);
                
                // Handle direct API response format (not JSON-RPC)
                if (isset($responseData['success']) && $responseData['success']) {
                    $odooId = $this->extractOdooId($responseData);
                    
                    if (!$odooId) {
                        Log::warning('Odoo API Response Missing ID', [
                            'response_data' => $responseData,
                            'partner_id' => $data['partner_id'] ?? null,
                            'application_id' => $dealerApplication->id ?? null
                        ]);
                    }
                    
                    return [
                        'success' => true,
                        'odoo_id' => $odooId,
                        'message' => $responseData['message'] ?? 'Verification submitted successfully'
                    ];
                }

                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Failed to submit verification'
                ];
            }

            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            return [
                'success' => false,
                'message' => 'Failed to submit verification to Odoo'
            ];

        } catch (OdooConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Odoo verification submission failed', [
                'partner_id' => $data['partner_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            throw new OdooConnectionException('Unable to submit verification to Odoo: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function prepareOdooPayload(array $data, DealerApplication $dealerApplication): array
    {
        $payload = [
            'partner_id' => $data['partner_id'],
            'seller_type' => $data['seller_type'],
            'dealership_name' => $data['dealership_name'],
            'owner_manager_name' => $data['owner_manager_name'],
            'vehicles_in_stock' => $data['vehicles_in_stock'] ?? 0,
            'years_in_business' => $data['years_in_business'] ?? 0,
            'phone_number' => $data['phone_number'],
            'email_address' => $data['email_address'],
            'city_address' => $data['city_address'],
            'subscription_plan_id' => $data['subscription_plan_id'],
        ];

        // Add authorized seller specific fields
        if ($data['seller_type'] === 'authorized') {
            $payload['automotive_brand'] = $data['automotive_brand'] ?? null;
            
            // Convert uploaded files to base64 for Odoo API
            if (isset($data['authorization_letter']) && $data['authorization_letter'] instanceof \Illuminate\Http\UploadedFile) {
                $payload['authorization_letter'] = base64_encode(file_get_contents($data['authorization_letter']->getRealPath()));
                $payload['authorization_letter_filename'] = $data['authorization_letter']->getClientOriginalName();
            }
            
            if (isset($data['business_license']) && $data['business_license'] instanceof \Illuminate\Http\UploadedFile) {
                $payload['business_license'] = base64_encode(file_get_contents($data['business_license']->getRealPath()));
                $payload['business_license_filename'] = $data['business_license']->getClientOriginalName();
            }
            
            $payload['business_license_name'] = $data['business_license_name'] ?? null;
        }

        return $payload;
    }

    /**
     * Store an uploaded file
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @return string
     */
    protected function storeUploadedFile(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs($directory, $filename, 'public');
        
        return $path;
    }

    protected function extractOdooId(array $responseData): ?string
    {

        
        $possiblePaths = [
            'data.id',           // Direct API format: {"data": {"id": 4}}
            'result.data.id',    // JSON-RPC format: {"result": {"data": {"id": 4}}}
            'result.id',         // JSON-RPC format: {"result": {"id": 4}}
            'result.data',       // JSON-RPC format: {"result": {"data": 4}}
            'id',                // Direct format: {"id": 4}
            'result'             // JSON-RPC format: {"result": 4}
        ];
        
        foreach ($possiblePaths as $path) {
            $value = data_get($responseData, $path);
            if ($value !== null && (is_numeric($value) || is_string($value))) {
                return (string) $value;
            }
        }
        
        return null;
    }

    public function updateVerificationStatus(int $applicationId, string $status, ?string $reason = null): array
    {
        try {
            $dealerApplication = DealerApplication::findOrFail($applicationId);
            
            DB::beginTransaction();

            switch ($status) {
                case 'approved':
                    $dealerApplication->approve();
                    $dealerApplication->user->update(['is_dealer' => 1]);
                    break;
                    
                case 'rejected':
                    $dealerApplication->reject($reason);
                    break;
                    
                case 'under_review':
                    $dealerApplication->setUnderReview();
                    break;
                    
                default:
                    throw new OdooServiceException('Invalid status: ' . $status);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Verification status updated successfully',
                'status' => $status
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update verification status', [
                'application_id' => $applicationId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function approveApplication(int $applicationId, ?int $reviewedBy = null): array
    {
        try {
            $application = DealerApplication::findOrFail($applicationId);
            
            if (!$application->isPending() && !$application->isUnderReview()) {
                throw new OdooServiceException('Application cannot be approved. Current status: ' . ($application->status?->display_name ?? 'Unknown'));
            }

            if (!$application->odoo_id) {
                throw new OdooServiceException('Application does not have an Odoo ID. Cannot approve without Odoo integration.');
            }

            DB::beginTransaction();

            $odooResponse = $this->notifyOdooApproval($application->odoo_id, $application);

            if (!$odooResponse['success']) {
                throw new OdooServiceException('Failed to notify Odoo about approval: ' . $odooResponse['message']);
            }

            $approvedStatus = Status::findByNameAndType('approved', 'dealer_application');
            
            $application->update([
                'status_id' => $approvedStatus?->id,
                'approved_at' => now(),
                'reviewed_by' => $reviewedBy,
                'rejection_reason' => null,
            ]);

            $application->user->update([
                'is_dealer' => true,
            ]);

            DB::commit();

            Log::info('Dealer application approved', [
                'application_id' => $applicationId,
                'odoo_id' => $application->odoo_id,
                'user_id' => $application->user_id,
                'reviewed_by' => $reviewedBy,
                'odoo_response' => $odooResponse,
            ]);

            return [
                'success' => true,
                'message' => 'Dealer application approved successfully',
                'application_id' => $applicationId,
                'odoo_id' => $application->odoo_id,
                'status' => 'approved',
                'user_is_dealer' => true,
                'odoo_notified' => true,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve dealer application', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
            ]);

            throw new OdooServiceException('Failed to approve application: ' . $e->getMessage());
        }
    }

    public function rejectApplication(int $applicationId, string $reason, ?int $reviewedBy = null): array
    {
        try {
            $application = DealerApplication::findOrFail($applicationId);
            
            if (!$application->isPending() && !$application->isUnderReview()) {
                throw new OdooServiceException('Application cannot be rejected. Current status: ' . ($application->status?->display_name ?? 'Unknown'));
            }

            if (!$application->odoo_id) {
                throw new OdooServiceException('Application does not have an Odoo ID. Cannot reject without Odoo integration.');
            }

            DB::beginTransaction();

            $odooResponse = $this->notifyOdooRejection($application->odoo_id, $application, $reason);

            if (!$odooResponse['success']) {
                throw new OdooServiceException('Failed to notify Odoo about rejection: ' . $odooResponse['message']);
            }

            $rejectedStatus = Status::findByNameAndType('rejected', 'dealer_application');
            
            $application->update([
                'status_id' => $rejectedStatus?->id,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewedBy,
                'approved_at' => null,
            ]);

            $application->user->update([
                'is_dealer' => false,
            ]);

            DB::commit();

            Log::info('Dealer application rejected', [
                'application_id' => $applicationId,
                'odoo_id' => $application->odoo_id,
                'user_id' => $application->user_id,
                'reason' => $reason,
                'reviewed_by' => $reviewedBy,
                'odoo_response' => $odooResponse,
            ]);

            return [
                'success' => true,
                'message' => 'Dealer application rejected successfully',
                'application_id' => $applicationId,
                'odoo_id' => $application->odoo_id,
                'status' => 'rejected',
                'reason' => $reason,
                'user_is_dealer' => false,
                'odoo_notified' => true,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject dealer application', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
            ]);

            throw new OdooServiceException('Failed to reject application: ' . $e->getMessage());
        }
    }

    protected function notifyOdooApproval(string $odooId, DealerApplication $application): array
    {
        try {
            $payload = [
                'odoo_id' => $odooId, // This is the partner_id from Odoo
                'action' => 'approve',
                'status' => 'approved',
                'approved_at' => now()->toISOString(),
                'reviewed_by' => $application->reviewed_by,
                'partner_id' => $application->user->partner_id, // Same as odoo_id
                'application_data' => [
                    'seller_type' => $application->seller_type,
                    'dealership_name' => $application->dealership_company_name,
                    'owner_manager_name' => $application->owner_manager_name,
                ]
            ];

            Log::info('Notifying Odoo about approval', [
                'odoo_id' => $odooId,
                'payload' => $payload
            ]);

            $jsonRpcPayload = [
                'jsonrpc' => '2.0',
                'id' => null,
                'method' => 'call',
                'params' => [
                    'service' => 'verification',
                    'method' => 'approve',
                    'args' => [$payload]
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->timeout(30)->post($this->baseUrl . '/jsonrpc', $jsonRpcPayload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Odoo approval notification successful', [
                    'odoo_id' => $odooId,
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message' => 'Odoo notified successfully',
                    'odoo_response' => $responseData
                ];
            } else {
                Log::error('Odoo approval notification failed', [
                    'odoo_id' => $odooId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Odoo notification failed: ' . $response->status(),
                    'status' => $response->status()
                ];
            }

        } catch (Exception $e) {
            Log::error('Odoo approval notification exception', [
                'odoo_id' => $odooId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Odoo notification exception: ' . $e->getMessage()
            ];
        }
    }

    protected function notifyOdooRejection(string $odooId, DealerApplication $application, string $reason): array
    {
        try {
            $payload = [
                'odoo_id' => $odooId, 
                'action' => 'reject',
                'status' => 'rejected',
                'rejected_at' => now()->toISOString(),
                'rejection_reason' => $reason,
                'reviewed_by' => $application->reviewed_by,
                'partner_id' => $application->user->partner_id,
                'application_data' => [
                    'seller_type' => $application->seller_type,
                    'dealership_name' => $application->dealership_company_name,
                    'owner_manager_name' => $application->owner_manager_name,
                ]
            ];

            Log::info('Notifying Odoo about rejection', [
                'odoo_id' => $odooId,
                'payload' => $payload
            ]);

            $jsonRpcPayload = [
                'jsonrpc' => '2.0',
                'id' => null,
                'method' => 'call',
                'params' => [
                    'service' => 'verification',
                    'method' => 'reject',
                    'args' => [$payload]
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->timeout(30)->post($this->baseUrl . '/jsonrpc', $jsonRpcPayload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Odoo rejection notification successful', [
                    'odoo_id' => $odooId,
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message' => 'Odoo notified successfully',
                    'odoo_response' => $responseData
                ];
            } else {
                Log::error('Odoo rejection notification failed', [
                    'odoo_id' => $odooId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Odoo notification failed: ' . $response->status(),
                    'status' => $response->status()
                ];
            }

        } catch (Exception $e) {
            Log::error('Odoo rejection notification exception', [
                'odoo_id' => $odooId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Odoo notification exception: ' . $e->getMessage()
            ];
        }
    }
}
