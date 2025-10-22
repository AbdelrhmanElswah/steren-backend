<?php

namespace App\Services;

use App\Models\User;
use App\Models\DealerApplication;
use App\Models\SubscriptionPlan;
use App\Models\Status;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooServiceException;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class DealerVerificationService
{
    protected $baseUrl;
    protected $database;
    protected $apiKey;
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->baseUrl = config('services.odoo.base_url');
        $this->database = config('services.odoo.database');
        $this->apiKey = config('services.odoo.api_key');
        $this->firebaseService = $firebaseService;
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

            $subscriptionPlan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

            // First, try to submit to Odoo BEFORE creating database record
            try {
                $odooResponse = $this->submitToOdoo($data, $user, $subscriptionPlan);

                if (!$odooResponse['success']) {
                    throw new OdooServiceException('Odoo submission failed: ' . ($odooResponse['message'] ?? 'Unknown error'));
                }

                // Only create dealer application if Odoo returns success
                $dealerApplication = $this->createDealerApplication($user, $data, $subscriptionPlan);
                
                $underReviewStatus = Status::findByNameAndType('under_review', 'dealer_application');
                
                // Use user ID as odoo_id (as requested)
                $userOdooId = (string) $user->id;
                
                $dealerApplication->update([
                    'status_id' => $underReviewStatus?->id,
                    'odoo_id' => $userOdooId,
                    'synced_with_odoo' => true,
                    'sync_error' => null
                ]);

                // Firebase sync can be added later if needed

                Log::info('Dealer application created and synced with Odoo successfully', [
                    'application_id' => $dealerApplication->id,
                    'odoo_id' => $userOdooId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Dealer verification submitted successfully to Odoo',
                    'application_id' => $dealerApplication->id,
                    'odoo_id' => $userOdooId,
                    'status' => 'under_review',
                    'synced_with_odoo' => true
                ];
            } catch (OdooConnectionException $e) {
                Log::error('Odoo connection failed', [
                    'partner_id' => $data['partner_id'],
                    'error' => $e->getMessage(),
                ]);

                throw new OdooServiceException('Failed to connect to Odoo: ' . $e->getMessage());
            }

        } catch (Exception $e) {
            // Only rollback if the local save itself failed
            
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
            $applicationAppliedStatus = Status::findByNameAndType('application_applied', 'dealer_application');
            
            $applicationData = [
                'partner_id' => $user->id,
                'odoo_id' => (string) $user->id,
                'seller_type' => $data['seller_type'],
                'owner_manager_name' => $data['owner_manager_name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email_address'],
                'dealership_company_name' => $data['dealership_name'],
                'vehicles_in_stock' => $data['vehicles_in_stock'] ?? null,
                'years_in_business' => $data['years_in_business'] ?? null,
                'city_address' => $data['city_address'],
                'subscription_plan_id' => $subscriptionPlan->id,
                'status_id' => $applicationAppliedStatus?->id,
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

        $dealerApplication = DealerApplication::create($applicationData);

        $this->firebaseService->syncDealerApplicationToFirebase(
            $dealerApplication->id,
            (string) $user->partner_id,
            [
                'application_id' => $dealerApplication->id,
                'status' => 'application_applied',
                'dealership_name' => $dealerApplication->dealership_company_name,
                'seller_type' => $dealerApplication->seller_type,
                'owner_name' => $dealerApplication->owner_manager_name,
                'vehicles_in_stock' => $dealerApplication->vehicles_in_stock,
                'years_in_business' => $dealerApplication->years_in_business,
                'created_at' => $dealerApplication->created_at->toISOString(),
            ]
        );

        return $dealerApplication;
    }

    protected function submitToOdoo(array $data, User $user, SubscriptionPlan $subscriptionPlan): array
    {
        try {
            $payload = $this->prepareOdooPayload($data, $user, $subscriptionPlan);

            $hasFiles = $this->hasFileUploads($data);
            
            if ($hasFiles) {
                $response = Http::timeout(30);
                
                // Add file attachments
                $attachments = $this->prepareFileAttachments($data);
                foreach ($attachments as $fieldName => $file) {
                    $response = $response->attach($fieldName, $file->getPathname(), $file->getClientOriginalName());
                }
                
                $response = $response->post($this->baseUrl . '/api/verification/submit', $payload);
            } else {
                $response = Http::timeout(30)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($this->baseUrl . '/api/verification/submit', $payload);
            }

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info('Odoo API Success Response - Full Details', [
                    'http_status' => $response->status(),
                    'response_data' => $responseData,
                    'success_field' => $responseData['success'] ?? 'NOT SET',
                    'partner_id' => $data['partner_id'] ?? null,
                    'partner_id' => $user->id ?? null
                ]);
                
                if (isset($responseData['success']) && $responseData['success'] === true) {
                    $odooId = $this->extractOdooId($responseData);
                    
                    if (!$odooId) {
                        Log::warning('Odoo API Response Missing ID', [
                            'response_data' => $responseData,
                            'partner_id' => $data['partner_id'] ?? null,
                            'partner_id' => $user->id ?? null
                        ]);
                    }
                    
                    return [
                        'success' => true,
                        'odoo_id' => $odooId,
                        'message' => $responseData['message'] ?? 'Verification submitted successfully'
                    ];
                }

                Log::warning('Odoo API returned 200 but success=false', [
                    'response_data' => $responseData,
                    'partner_id' => $data['partner_id'] ?? null,
                    'partner_id' => $user->id ?? null
                ]);
                
                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Failed to submit verification'
                ];
            }

            $responseData = $response->json();
            
            if ($response->status() >= 500) {
                throw new OdooConnectionException('Odoo service is temporarily unavailable');
            }

            $errorMessage = $responseData['error'] ?? 'Failed to submit verification to Odoo';
            $errorCode = $responseData['error_code'] ?? null;
            $errorDetails = $responseData['details'] ?? null;

            Log::warning('Odoo API Error Response', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'details' => $errorDetails,
                'partner_id' => $data['partner_id'] ?? null
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'details' => $errorDetails,
                'status' => $response->status()
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

    protected function prepareOdooPayload(array $data, User $user, SubscriptionPlan $subscriptionPlan): array
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
            'subscription_plan_id' => $subscriptionPlan->odoo_id, 
        ];

        if ($data['seller_type'] === 'authorized') {
            $payload['automotive_brand'] = $data['automotive_brand'] ?? null;
            $payload['business_license_name'] = $data['business_license_name'] ?? null;
            
        }

        return $payload;
    }

    /**
     * Check if the data contains file uploads
     */
    protected function hasFileUploads(array $data): bool
    {
        return isset($data['authorization_letter']) || isset($data['business_license']);
    }

    /**
     * Prepare file attachments for multipart/form-data
     */
    protected function prepareFileAttachments(array $data): array
    {
        $attachments = [];
        
        if (isset($data['authorization_letter']) && $data['authorization_letter'] instanceof \Illuminate\Http\UploadedFile) {
            $attachments['authorization_letter'] = $data['authorization_letter'];
        }
        
        if (isset($data['business_license']) && $data['business_license'] instanceof \Illuminate\Http\UploadedFile) {
            $attachments['business_license'] = $data['business_license'];
        }
        
        return $attachments;
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
            
            $allowedStatuses = ['application_applied', 'pending', 'under_review'];
            if (!in_array($application->status?->name, $allowedStatuses)) {
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

            $this->firebaseService->updateDealerApplicationStatus(
                $application->odoo_id,
                $applicationId,
                'approved'
            );

            Log::info('Dealer application approved', [
                'application_id' => $applicationId,
                'odoo_id' => $application->odoo_id,
                'partner_id' => $application->partner_id,
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
            
            $allowedStatuses = ['application_applied', 'pending', 'under_review'];
            if (!in_array($application->status?->name, $allowedStatuses)) {
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

            $this->firebaseService->updateDealerApplicationStatus(
                $application->odoo_id,
                $applicationId,
                'rejected',
                $reason
            );

            Log::info('Dealer application rejected', [
                'application_id' => $applicationId,
                'odoo_id' => $application->odoo_id,
                'partner_id' => $application->partner_id,
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
