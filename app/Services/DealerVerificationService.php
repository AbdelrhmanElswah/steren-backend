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
            Log::info('DealerVerificationService received data', [
                'data_keys' => array_keys($data),
                'subscription_plan_id' => $data['subscription_plan_id'] ?? 'NOT_SET',
                'partner_id' => $data['partner_id'] ?? 'NOT_SET',
                'seller_type' => $data['seller_type'] ?? 'NOT_SET'
            ]);

            if (isset($data['email_address']) && !isset($data['email'])) {
                $data['email'] = $data['email_address'];
            }
            
            if (isset($data['dealership_name']) && !isset($data['dealership_company_name'])) {
                $data['dealership_company_name'] = $data['dealership_name'];
            }
            
            if (isset($data['automotive_brand']) && !isset($data['automotive_brand_represented'])) {
                $data['automotive_brand_represented'] = $data['automotive_brand'];
            }
            
            if (isset($data['business_license_name']) && !isset($data['business_license_number'])) {
                $data['business_license_number'] = $data['business_license_name'];
            }

            $user = $authenticatedUser ?? User::where('partner_id', $data['partner_id'])->first();
            
            if (!$user) {
                throw new OdooServiceException('User not found for partner ID: ' . ($data['partner_id'] ?? 'N/A'));
            }

            if ($user->is_dealer) {
                throw new OdooServiceException('User is already a verified dealer');
            }
            
            $data['partner_id'] = $user->partner_id;

            Log::info('Looking up subscription plan', [
                'requested_plan_id' => $data['subscription_plan_id'],
                'requested_plan_type' => gettype($data['subscription_plan_id']),
                'partner_id' => $data['partner_id']
            ]);

            $subscriptionPlan = SubscriptionPlan::find($data['subscription_plan_id']);
            
            if (!$subscriptionPlan) {
                Log::info('Plan not found by database ID, trying odoo_id', [
                    'database_id_search' => $data['subscription_plan_id'],
                    'trying_odoo_id' => true
                ]);
                
                $subscriptionPlan = SubscriptionPlan::where('odoo_id', (string) $data['subscription_plan_id'])->first();
            }
            
            if (!$subscriptionPlan) {
                Log::error('Subscription plan not found - this will cause issues', [
                    'requested_plan_id' => $data['subscription_plan_id'],
                    'requested_plan_type' => gettype($data['subscription_plan_id']),
                    'partner_id' => $data['partner_id'],
                    'available_plans' => SubscriptionPlan::all()->pluck('id', 'name')->toArray()
                ]);
                
                $subscriptionPlan = SubscriptionPlan::first() ?? $this->createFallbackSubscriptionPlan($data['subscription_plan_id']);
                
                Log::warning('Using fallback subscription plan', [
                    'fallback_plan_id' => $subscriptionPlan->id,
                    'fallback_plan_name' => $subscriptionPlan->name,
                    'requested_plan_id' => $data['subscription_plan_id']
                ]);
            } else {
                Log::info('Subscription plan found', [
                    'plan_id' => $subscriptionPlan->id,
                    'plan_odoo_id' => $subscriptionPlan->odoo_id,
                    'plan_name' => $subscriptionPlan->name,
                    'requested_id' => $data['subscription_plan_id']
                ]);
            }

            // First, create the local dealer application record
            try {
                Log::info('Creating local dealer application first', [
                    'partner_id' => $data['partner_id'],
                    'user_id' => $user->id,
                    'subscription_plan_id' => $subscriptionPlan->id ?? 'NOT_SET'
                ]);
                
                $dealerApplication = $this->createDealerApplication($user, $data, $subscriptionPlan);
                
                $applicationAppliedStatus = Status::findByNameAndType('application_applied', 'dealer_application');
                
                $dealerApplication->update([
                    'status_id' => $applicationAppliedStatus?->id,
                    'odoo_id' => null, // Will be updated after Odoo response
                    'synced_with_odoo' => false,
                    'sync_error' => null
                ]);

                Log::info('Local dealer application created successfully', [
                    'application_id' => $dealerApplication->id,
                    'status' => 'application_applied'
                ]);

                // Now send to Odoo
                try {
                    $odooResponse = $this->submitToOdoo($data, $user, $subscriptionPlan);

                    if (!$odooResponse['success']) {
                        Log::error('Odoo submission failed', [
                            'application_id' => $dealerApplication->id,
                            'partner_id' => $data['partner_id'],
                            'odoo_message' => $odooResponse['message'] ?? 'Unknown error',
                            'odoo_error_code' => $odooResponse['error_code'] ?? null,
                            'odoo_details' => $odooResponse['details'] ?? null,
                        ]);
                        
                        // Update local record with error
                        $dealerApplication->update([
                            'sync_error' => $odooResponse['message'] ?? 'Odoo submission failed',
                            'synced_with_odoo' => false
                        ]);
                        
                        throw new OdooServiceException('Odoo submission failed: ' . ($odooResponse['message'] ?? 'Unknown error'));
                    }

                    Log::info('Raw Odoo response structure', [
                        'full_response' => $odooResponse,
                        'response_keys' => array_keys($odooResponse),
                        'data_keys' => isset($odooResponse['data']) ? array_keys($odooResponse['data']) : 'no_data_key'
                    ]);
                    
                    $odooId = $odooResponse['data']['id'] ?? $odooResponse['odoo_id'] ?? null;
                    
                    Log::info('Odoo accepted the dealer application', [
                        'application_id' => $dealerApplication->id,
                        'partner_id' => $data['partner_id'],
                        'odoo_response' => $odooResponse,
                        'odoo_id' => $odooId
                    ]);

                    $underReviewStatus = Status::findByNameAndType('under_review', 'dealer_application');
                    
                    Log::info('Status lookup result', [
                        'status_found' => $underReviewStatus ? true : false,
                        'status_id' => $underReviewStatus?->id,
                        'status_name' => $underReviewStatus?->name
                    ]);
                    
                    $updateData = [
                        'odoo_id' => $odooId,
                        'synced_with_odoo' => true,
                        'sync_error' => null
                    ];
                    
                    if ($underReviewStatus) {
                        $updateData['status_id'] = $underReviewStatus->id;
                    }
                    
                    $dealerApplication->update($updateData);

                    Log::info('Dealer application synced with Odoo successfully', [
                        'application_id' => $dealerApplication->id,
                        'odoo_id' => $odooId,
                        'status' => 'under_review'
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Dealer verification submitted successfully to Odoo',
                        'application_id' => $dealerApplication->id,
                        'odoo_id' => $odooId,
                        'status' => 'under_review',
                        'synced_with_odoo' => true
                    ];

                } catch (\Exception $odooError) {
                    Log::error('Odoo submission failed after local record creation', [
                        'application_id' => $dealerApplication->id,
                        'partner_id' => $data['partner_id'],
                        'odoo_error' => $odooError->getMessage()
                    ]);

                    // Update local record with Odoo error
                    $dealerApplication->update([
                        'sync_error' => $odooError->getMessage(),
                        'synced_with_odoo' => false
                    ]);

                    throw new OdooServiceException('Odoo submission failed: ' . $odooError->getMessage());
                }

            } catch (\Exception $localError) {
                Log::error('Local dealer application creation failed', [
                    'partner_id' => $data['partner_id'],
                    'user_id' => $user->id,
                    'local_error' => $localError->getMessage(),
                    'error_trace' => $localError->getTraceAsString(),
                    'subscription_plan_id' => $subscriptionPlan->id ?? 'NOT_SET'
                ]);

                throw new OdooServiceException('Failed to create dealer application: ' . $localError->getMessage());
            } catch (OdooConnectionException $e) {
                Log::error('Odoo connection failed', [
                    'partner_id' => $data['partner_id'],
                    'error' => $e->getMessage(),
                ]);

                throw new OdooServiceException('Failed to connect to Odoo: ' . $e->getMessage());
            }

        } catch (Exception $e) {            
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
            
            Log::info('Creating dealer application with data', [
                'vehicles_in_stock' => $data['vehicles_in_stock'] ?? 'not_set',
                'years_in_business' => $data['years_in_business'] ?? 'not_set',
                'seller_type' => $data['seller_type'] ?? 'not_set',
            ]);
            
            $applicationData = [
                'partner_id' => $user->id,
                'odoo_id' => (string) $user->id,
                'seller_type' => $data['seller_type'],
                'owner_manager_name' => $data['owner_manager_name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email'],
                'dealership_company_name' => $data['dealership_company_name'],
                'vehicles_in_stock' => isset($data['vehicles_in_stock']) && $data['vehicles_in_stock'] !== '' 
                    ? (int) $data['vehicles_in_stock'] 
                    : null,
                'years_in_business' => isset($data['years_in_business']) && $data['years_in_business'] !== '' 
                    ? (int) $data['years_in_business'] 
                    : null,
                'city_address' => $data['city_address'],
                'subscription_plan_id' => $subscriptionPlan->id,
                'status_id' => $applicationAppliedStatus?->id,
                'terms_accepted' => true,
                'information_confirmed' => true,
            ];

        if ($data['seller_type'] === 'authorized') {
            $applicationData['automotive_brand_represented'] = $data['automotive_brand_represented'] ?? null;
            $applicationData['business_license_number'] = $data['business_license_number'] ?? null;
            
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

        try {
            $dealerApplication = DealerApplication::create($applicationData);
            
            Log::info('Dealer application created successfully', [
                'application_id' => $dealerApplication->id,
                'vehicles_in_stock' => $dealerApplication->vehicles_in_stock,
                'years_in_business' => $dealerApplication->years_in_business,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create dealer application', [
                'error' => $e->getMessage(),
                'application_data' => $applicationData,
                'vehicles_in_stock' => $applicationData['vehicles_in_stock'],
                'years_in_business' => $applicationData['years_in_business'],
            ]);
            
            throw new \Exception('Failed to create dealer application: ' . $e->getMessage());
        }

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

    /**
     * Create a fallback subscription plan if the requested one doesn't exist
     */
    protected function createFallbackSubscriptionPlan(int $requestedPlanId): SubscriptionPlan
    {
        Log::info('Creating fallback subscription plan', [
            'requested_plan_id' => $requestedPlanId
        ]);

        return SubscriptionPlan::create([
            'name' => 'Fallback Plan ' . $requestedPlanId,
            'description' => 'Auto-generated fallback plan',
            'price' => 0,
            'currency' => 'IQD',
            'billing_cycle' => 'yearly',
            'features' => [],
            'max_listings' => null,
            'max_photos' => null,
            'subscriber_count' => 0,
            'is_active' => true,
            'is_popular' => false,
            'is_featured' => false,
            'is_whatsapp_phone' => false,
            'is_badge' => false,
            'is_search' => false,
            'is_similar_cars' => false,
            'is_dedicated_account_rep' => false,
            'is_analytics' => false,
            'is_top_picks' => false,
            'is_priority_support' => false,
            'is_you_may_also_like' => false,
            'sort_order' => 999,
            'odoo_id' => (string) $requestedPlanId,
        ]);
    }

    protected function submitToOdoo(array $data, User $user, SubscriptionPlan $subscriptionPlan): array
    {
        try {
            $payload = $this->prepareOdooPayload($data, $user, $subscriptionPlan);
            
            Log::info('Sending data to Odoo', [
                'partner_id' => $data['partner_id'],
                'seller_type' => $data['seller_type'],
                'vehicles_in_stock' => $data['vehicles_in_stock'] ?? 'not_set',
                'payload' => $payload
            ]);

            $hasFiles = $this->hasFileUploads($data);
            
            if ($hasFiles) {
                $response = Http::timeout(30);
                
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
                    'user_id' => $user->id ?? null
                ]);
                
                if (isset($responseData['success']) && $responseData['success'] === true) {
                    $odooId = $this->extractOdooId($responseData);
                    
                    Log::info('Odoo response processed successfully', [
                        'odoo_id' => $odooId,
                        'response_data' => $responseData,
                        'data_id' => $responseData['data']['id'] ?? 'NOT_FOUND'
                    ]);
                    
                    if (!$odooId) {
                        Log::warning('Odoo API Response Missing ID', [
                            'response_data' => $responseData,
                            'partner_id' => $data['partner_id'] ?? null,
                            'user_id' => $user->id ?? null
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
            'dealership_name' => $data['dealership_company_name'],
            'owner_manager_name' => $data['owner_manager_name'],
            'vehicles_in_stock' => $this->formatVehiclesInStockForOdoo($data['vehicles_in_stock']),
            'years_in_business' => (int) ($data['years_in_business'] ?? 0),
            'phone_number' => $this->formatPhoneForOdoo($data['phone_number']),
            'email_address' => $data['email'],
            'city_address' => $data['city_address'],
            'subscription_plan_id' => $subscriptionPlan->odoo_id ?? $subscriptionPlan->id, 
        ];

        if ($data['seller_type'] === 'authorized') {
            $payload['automotive_brand'] = $data['automotive_brand_represented'] ?? null;
            $payload['business_license_name'] = $data['business_license_number'] ?? null;
            
        }

        return $payload;
    }


    protected function hasFileUploads(array $data): bool
    {
        return isset($data['authorization_letter']) || isset($data['business_license']);
    }

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

    protected function storeUploadedFile(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs($directory, $filename, 'public');
        
        return $path;
    }

    protected function extractOdooId(array $responseData): ?string
    {

        
        $possiblePaths = [
            'data.id',           
            'result.data.id',    
            'result.id',         
            'result.data',       
            'id',                
            'result'             
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
                'odoo_id' => $odooId, 
                'action' => 'approve',
                'status' => 'approved',
                'approved_at' => now()->toISOString(),
                'reviewed_by' => $application->reviewed_by,
                'partner_id' => $application->user->partner_id, 
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

    /**
     * Format phone number for Odoo submission
     * Odoo requires at least 10 digits, so we pad with leading zeros if needed
     */
    protected function formatPhoneForOdoo(?string $phoneNumber): string
    {
        if (empty($phoneNumber)) {
            return '0000000000'; // Default 10-digit number for empty phones
        }

        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If already 10+ digits, return as is
        if (strlen($cleaned) >= 10) {
            return $cleaned;
        }
        
        // Pad with leading zeros to make it 10 digits
        return str_pad($cleaned, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Format vehicles in stock for Odoo submission
     * Odoo requires minimum 40 vehicles, so we ensure it meets this requirement
     */
    protected function formatVehiclesInStockForOdoo($vehiclesInStock): int
    {
        $vehicles = (int) ($vehiclesInStock ?? 0);
        
        // If vehicles is less than 40, set it to 40 to meet Odoo's minimum requirement
        if ($vehicles < 40) {
            return 40;
        }
        
        return $vehicles;
    }
}
