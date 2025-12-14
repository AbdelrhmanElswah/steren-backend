<?php

namespace App\Services;

use App\Models\User;
use App\Models\DealerApplication;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\Status;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooServiceException;
use App\Services\FirebaseService;
use App\Services\UnifiedNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class DealerVerificationService
{
    protected $baseUrl;
    protected $database;
    protected $apiKey;
    protected FirebaseService $firebaseService;
    protected UnifiedNotificationService $notificationService;

    public function __construct(
        FirebaseService $firebaseService,
        UnifiedNotificationService $notificationService
    ) {
        $this->baseUrl = config('services.odoo.base_url');
        $this->database = config('services.odoo.database');
        $this->apiKey = config('services.odoo.api_key');
        $this->firebaseService = $firebaseService;
        $this->notificationService = $notificationService;
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

            // Use authenticated user if provided, otherwise fallback to partner_id lookup (for backward compatibility)
            $user = $authenticatedUser;
            
            if (!$user && isset($data['partner_id'])) {
                $user = User::where('partner_id', $data['partner_id'])->first();
            }

            if (!$user) {
                throw new OdooServiceException('User not found. Authentication required.');
            }

            if ($user->is_dealer) {
                throw new OdooServiceException('User is already a verified dealer');
            }

            // Ensure partner_id is set from authenticated user (security: don't trust request data)
            $data['partner_id'] = $user->partner_id ?? $user->id;

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

                // Create notification for the user (database + optional Firebase)
                try {
                    $this->notificationService->createDealerApplicationSubmittedNotification($dealerApplication);
                } catch (Exception $e) {
                    // Log error but don't fail the submission process
                    Log::error('Failed to create dealer application submitted notification', [
                        'application_id' => $dealerApplication->id,
                        'error' => $e->getMessage()
                    ]);
                }

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
                        'odoo_id_from_response' => $odooResponse['odoo_id'] ?? 'NOT_SET'
                    ]);

                    // Extract odoo_id from response (submitToOdoo already extracts it)
                    $odooId = $odooResponse['odoo_id'] ?? null;

                    // Validate that we received an odoo_id - this is critical!
                    if (!$odooId) {
                        Log::error('Odoo submission succeeded but odoo_id is missing', [
                            'application_id' => $dealerApplication->id,
                            'partner_id' => $data['partner_id'],
                            'odoo_response' => $odooResponse,
                        ]);

                        // Update local record with error
                        $dealerApplication->update([
                            'sync_error' => 'Odoo submission succeeded but odoo_id was not returned in response',
                            'synced_with_odoo' => false
                        ]);

                        throw new OdooServiceException('Odoo submission succeeded but odoo_id was not returned. Cannot proceed without odoo_id.');
                    }

                    Log::info('Odoo accepted the dealer application with odoo_id', [
                        'application_id' => $dealerApplication->id,
                        'partner_id' => $data['partner_id'],
                        'odoo_id' => $odooId,
                        'odoo_id_type' => gettype($odooId)
                    ]);

                    $underReviewStatus = Status::findByNameAndType('under_review', 'dealer_application');

                    Log::info('Status lookup result', [
                        'status_found' => $underReviewStatus ? true : false,
                        'status_id' => $underReviewStatus?->id,
                        'status_name' => $underReviewStatus?->name
                    ]);

                    // Ensure odoo_id is stored as string for consistency
                    $odooIdString = (string) $odooId;

                    $updateData = [
                        'odoo_id' => $odooIdString,
                        'synced_with_odoo' => true,
                        'sync_error' => null
                    ];

                    if ($underReviewStatus) {
                        $updateData['status_id'] = $underReviewStatus->id;
                    }

                    $dealerApplication->update($updateData);

                    // Verify odoo_id was saved correctly
                    $dealerApplication->refresh();
                    if ($dealerApplication->odoo_id !== $odooIdString) {
                        Log::error('Failed to save odoo_id to dealer application', [
                            'application_id' => $dealerApplication->id,
                            'expected_odoo_id' => $odooIdString,
                            'saved_odoo_id' => $dealerApplication->odoo_id,
                        ]);
                        throw new OdooServiceException('Failed to save odoo_id to dealer application');
                    }

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
                'city_id' => isset($data['city_id']) && $data['city_id'] ? (int) $data['city_id'] : null,
                'subscription_plan_id' => $subscriptionPlan->id,
                'status_id' => $applicationAppliedStatus?->id,
                'terms_accepted' => true,
                'information_confirmed' => true,
            ];

        if ($data['seller_type'] === 'authorized') {
            $applicationData['automotive_brand_represented'] = $data['automotive_brand_represented'] ?? null;
            $applicationData['brand_id'] = $data['brand_id'] ?? null;
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
                // When files are present, send everything as multipart/form-data
                // Start building the request
                $request = Http::timeout(30);
                
                // Add file attachments using attach() method
                $attachments = $this->prepareFileAttachments($data);
                foreach ($attachments as $fieldName => $file) {
                    // Attach the file with its original name using file resource for memory efficiency
                    $request = $request->attach(
                        $fieldName,
                        fopen($file->getPathname(), 'r'),
                        $file->getClientOriginalName()
                    );
                    
                    // Add filename field to payload - use from request data if provided, otherwise use file's original name
                    $filenameField = $fieldName . '_filename';
                    if (isset($data[$filenameField]) && !empty($data[$filenameField])) {
                        $payload[$filenameField] = $data[$filenameField];
                    } else {
                        $payload[$filenameField] = $file->getClientOriginalName();
                    }
                }
                
                // Send as multipart/form-data with form fields in payload
                // When using attach(), Laravel automatically sends as multipart/form-data
                // and the second parameter becomes form fields
                $response = $request->post($this->baseUrl . '/api/verification/submit', $payload);
            } else {
                // When no files, send as JSON
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

                    // Ensure odoo_id is not null - this is critical for approval flow
                    if (!$odooId) {
                        Log::error('extractOdooId returned null - cannot proceed without odoo_id', [
                            'response_data' => $responseData,
                            'partner_id' => $data['partner_id'] ?? null,
                            'user_id' => $user->id ?? null,
                            'response_structure' => [
                                'has_data' => isset($responseData['data']),
                                'data_keys' => isset($responseData['data']) ? array_keys($responseData['data']) : [],
                                'data_id' => $responseData['data']['id'] ?? 'NOT_FOUND',
                            ]
                        ]);

                        return [
                            'success' => false,
                            'message' => 'Odoo returned success but did not provide an ID. Response structure may have changed.',
                            'error_code' => 'MISSING_ODOO_ID',
                            'response_data' => $responseData
                        ];
                    }

                    return [
                        'success' => true,
                        'odoo_id' => (string) $odooId, // Ensure it's a string
                        'data' => $responseData['data'] ?? null, // Include full data for debugging
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
            // If brand_id is provided, get the Odoo brand_id from CarBrand model
            if (isset($data['brand_id']) && !empty($data['brand_id'])) {
                $carBrand = \App\Models\CarBrand::find($data['brand_id']);
                if ($carBrand && $carBrand->odoo_id) {
                    $payload['brand_id'] = $carBrand->odoo_id;
                }
            }
            
            // Keep automotive_brand as fallback for backward compatibility
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
            'data.id',           // Standard Odoo response: {success: true, data: {id: 110}}
            'result.data.id',    // Alternative response format
            'result.id',         // Direct result format
            'result.data',       // Result data as ID
            'id',                // Direct ID field
            'result'             // Result as ID
        ];

        foreach ($possiblePaths as $path) {
            $value = data_get($responseData, $path);
            if ($value !== null && (is_numeric($value) || is_string($value))) {
                $extractedId = (string) $value;
                Log::info('Successfully extracted odoo_id from response', [
                    'path' => $path,
                    'odoo_id' => $extractedId,
                    'value_type' => gettype($value)
                ]);
                return $extractedId;
            }
        }

        // Log detailed information about why extraction failed
        Log::warning('Failed to extract odoo_id from Odoo response', [
            'response_structure' => [
                'top_level_keys' => array_keys($responseData),
                'has_data' => isset($responseData['data']),
                'data_type' => isset($responseData['data']) ? gettype($responseData['data']) : 'not_set',
                'data_keys' => isset($responseData['data']) && is_array($responseData['data']) ? array_keys($responseData['data']) : 'not_array',
                'data_id_value' => $responseData['data']['id'] ?? 'NOT_FOUND',
                'has_result' => isset($responseData['result']),
                'result_type' => isset($responseData['result']) ? gettype($responseData['result']) : 'not_set',
            ],
            'checked_paths' => $possiblePaths,
            'full_response_sample' => json_encode($responseData, JSON_PRETTY_PRINT)
        ]);

        return null;
    }

    public function updateVerificationStatus(int $applicationId, string $status, ?string $reason = null): array
    {
        try {
            $dealerApplication = DealerApplication::with('user')->findOrFail($applicationId);

            DB::beginTransaction();

            switch ($status) {
                case 'approved':
                    // Ensure user relationship exists
                    if (!$dealerApplication->user) {
                        throw new OdooServiceException('Dealer application does not have an associated user. partner_id: ' . $dealerApplication->partner_id);
                    }

                    $dealerApplication->approve();
                    $dealerApplication->user->update(['is_dealer' => 1]);
                    
                    // Create default studio using robust method with odoo_id
                    if (!$dealerApplication->odoo_id) {
                        throw new OdooServiceException('Cannot create studio: dealer application has no odoo_id');
                    }

                    try {
                        $studio = $this->createStudioForApprovedDealer($dealerApplication->odoo_id);
                        Log::info('Studio creation completed successfully during status update', [
                            'application_id' => $applicationId,
                            'odoo_id' => $dealerApplication->odoo_id,
                            'user_id' => $dealerApplication->user->id,
                            'studio_id' => $studio?->id,
                            'studio_created' => $studio ? true : false,
                        ]);
                    } catch (Exception $studioError) {
                        // Log error and re-throw to ensure we know about the issue
                        Log::error('Failed to create default studio during status update', [
                            'application_id' => $applicationId,
                            'odoo_id' => $dealerApplication->odoo_id,
                            'user_id' => $dealerApplication->user->id,
                            'error' => $studioError->getMessage(),
                            'trace' => $studioError->getTraceAsString(),
                        ]);
                        throw new OdooServiceException('Status update succeeded but studio creation failed: ' . $studioError->getMessage());
                    }
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
            $application = DealerApplication::with('user')->findOrFail($applicationId);

            $allowedStatuses = ['application_applied', 'pending', 'under_review'];
            if (!in_array($application->status?->name, $allowedStatuses)) {
                throw new OdooServiceException('Application cannot be approved. Current status: ' . ($application->status?->display_name ?? 'Unknown'));
            }

            if (!$application->odoo_id) {
                throw new OdooServiceException('Application does not have an Odoo ID. Cannot approve without Odoo integration.');
            }

            // Ensure user relationship exists
            if (!$application->user) {
                throw new OdooServiceException('Dealer application does not have an associated user. partner_id: ' . $application->partner_id);
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

            // Create default studio using robust method with odoo_id
            try {
                $studio = $this->createStudioForApprovedDealer($application->odoo_id);
                Log::info('Studio creation completed successfully during approval', [
                    'application_id' => $applicationId,
                    'odoo_id' => $application->odoo_id,
                    'user_id' => $application->user->id,
                    'studio_id' => $studio?->id,
                    'studio_created' => $studio ? true : false,
                ]);
            } catch (Exception $studioError) {
                // Log error but don't fail the approval - studio can be created later
                Log::error('Failed to create default studio during approval', [
                    'application_id' => $applicationId,
                    'odoo_id' => $application->odoo_id,
                    'user_id' => $application->user->id,
                    'error' => $studioError->getMessage(),
                    'trace' => $studioError->getTraceAsString(),
                ]);
                // Re-throw to ensure we know about the issue
                // Approval will fail if studio creation fails - this ensures data consistency
                throw new OdooServiceException('Approval succeeded but studio creation failed: ' . $studioError->getMessage());
            }

            // Auto-complete payment when application is approved
            // This creates the subscription record using the same logic as payment completion
            // Can be easily disabled by setting AUTO_COMPLETE_PAYMENT_ON_APPROVAL=false in .env
            if (config('services.dealer.auto_complete_payment_on_approval', true)) {
                $subscription = $this->completePaymentForApplication($application, [
                    'dealer_application_id' => $application->id,
                    'auto_completed_on_approval' => true,
                ]);
                
                if (!$subscription) {
                    Log::warning('Failed to create subscription on approval (non-critical)', [
                        'application_id' => $applicationId,
                        'subscription_plan_id' => $application->subscription_plan_id,
                    ]);
                    // Don't throw exception - approval can still succeed without subscription
                }
            }

            DB::commit();

            // Create notification for the user (database + optional Firebase)
            try {
                $this->notificationService->createDealerApplicationApprovedNotification($application);
            } catch (Exception $e) {
                // Log error but don't fail the approval process
                Log::error('Failed to create dealer application approval notification', [
                    'application_id' => $applicationId,
                    'error' => $e->getMessage()
                ]);
            }

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
                'rejected_at' => now(),
            ]);

            $application->user->update([
                'is_dealer' => false,
            ]);

            DB::commit();

            // Create notification for the user (database + optional Firebase)
            try {
                $this->notificationService->createDealerApplicationRejectedNotification($application, $reason);
            } catch (Exception $e) {
                // Log error but don't fail the rejection process
                Log::error('Failed to create dealer application rejection notification', [
                    'application_id' => $applicationId,
                    'error' => $e->getMessage()
                ]);
            }

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

    /**
     * Robust method to create default studio for dealer after approval
     * Uses odoo_id to ensure we have the correct dealer application
     * This method is idempotent - can be called multiple times safely
     * 
     * @param string $odooId The Odoo ID of the dealer application
     * @return \App\Models\DealerStudio|null The created or existing studio
     * @throws \Exception If studio creation fails
     */
    public function createStudioForApprovedDealer(string $odooId): ?\App\Models\DealerStudio
    {
        try {
            // Find dealer application by odoo_id with user relationship
            $dealerApplication = DealerApplication::with('user')->where('odoo_id', $odooId)->first();

            if (!$dealerApplication) {
                Log::warning('Dealer application not found by odoo_id for studio creation', [
                    'odoo_id' => $odooId,
                ]);
                throw new OdooServiceException('Dealer application not found with odoo_id: ' . $odooId);
            }

            if (!$dealerApplication->user) {
                Log::error('Dealer application has no associated user', [
                    'odoo_id' => $odooId,
                    'application_id' => $dealerApplication->id,
                    'partner_id' => $dealerApplication->partner_id,
                ]);
                throw new OdooServiceException('Dealer application does not have an associated user. partner_id: ' . $dealerApplication->partner_id);
            }

            $user = $dealerApplication->user;

            // Comprehensive duplicate checking - check multiple ways to prevent duplicates
            // 1. Check by dealer_id (any active studio)
            $existingStudio = \App\Models\DealerStudio::where('dealer_id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate() // Lock to prevent race conditions
                ->first();

            if ($existingStudio) {
                Log::info('Dealer already has an active studio, returning existing studio', [
                    'dealer_id' => $user->id,
                    'existing_studio_id' => $existingStudio->id,
                    'studio_name' => $existingStudio->name,
                    'odoo_id' => $odooId,
                ]);
                return $existingStudio;
            }

            // 2. Check by odoo_id if stored in studio (additional safety check)
            if (\Illuminate\Support\Facades\Schema::hasColumn('dealer_studios', 'odoo_id')) {
                $existingStudioByOdoo = \App\Models\DealerStudio::where('odoo_id', $odooId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($existingStudioByOdoo) {
                    Log::info('Studio already exists with this odoo_id, returning existing studio', [
                        'odoo_id' => $odooId,
                        'existing_studio_id' => $existingStudioByOdoo->id,
                        'dealer_id' => $user->id,
                    ]);
                    return $existingStudioByOdoo;
                }
            }

            // 3. Double-check by dealer_id after lock (race condition protection)
            $doubleCheckStudio = \App\Models\DealerStudio::where('dealer_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($doubleCheckStudio) {
                Log::info('Studio was created by another process, returning existing studio', [
                    'dealer_id' => $user->id,
                    'existing_studio_id' => $doubleCheckStudio->id,
                    'odoo_id' => $odooId,
                ]);
                return $doubleCheckStudio;
            }

            // Get studio name from dealer application
            $studioName = $dealerApplication->dealership_company_name 
                ?: $user->full_name 
                ?: 'Dealer Studio';

            Log::info('Creating default studio for approved dealer', [
                'odoo_id' => $odooId,
                'dealer_id' => $user->id,
                'application_id' => $dealerApplication->id,
                'studio_name' => $studioName,
                'dealership_company_name' => $dealerApplication->dealership_company_name,
            ]);

            // Create studio within a transaction for safety
            $studio = DB::transaction(function () use ($user, $dealerApplication, $studioName, $odooId) {
                // Final check inside transaction
                $finalCheck = \App\Models\DealerStudio::where('dealer_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                if ($finalCheck) {
                    return $finalCheck;
                }

                // Get valid city_id - prioritize application's city_id, then user's city_id, then fallback
                $cityId = null;
                
                // Priority 1: Use application's city_id if provided and valid
                if ($dealerApplication->city_id) {
                    $cityExists = \App\Models\City::where('id', $dealerApplication->city_id)->exists();
                    if ($cityExists) {
                        $cityId = $dealerApplication->city_id;
                        Log::info('Using city_id from dealer application', [
                            'city_id' => $cityId,
                            'application_id' => $dealerApplication->id,
                        ]);
                    } else {
                        Log::warning('Dealer application has invalid city_id, will try fallback', [
                            'application_id' => $dealerApplication->id,
                            'invalid_city_id' => $dealerApplication->city_id,
                        ]);
                    }
                }

                // Priority 2: Fallback to user's city_id if application doesn't have one
                if (!$cityId && $user->city_id) {
                    $cityExists = \App\Models\City::where('id', $user->city_id)->exists();
                    if ($cityExists) {
                        $cityId = $user->city_id;
                        Log::info('Using city_id from user as fallback', [
                            'city_id' => $cityId,
                            'user_id' => $user->id,
                        ]);
                    } else {
                        Log::warning('User has invalid city_id, will try to find valid city', [
                            'user_id' => $user->id,
                            'invalid_city_id' => $user->city_id,
                        ]);
                    }
                }

                // Priority 3: If no valid city_id from application or user, try to find first available city
                if (!$cityId) {
                    $firstCity = \App\Models\City::orderBy('id')->first();
                    if ($firstCity) {
                        $cityId = $firstCity->id;
                        Log::info('Using first available city as fallback', [
                            'city_id' => $cityId,
                            'city_name' => $firstCity->name,
                        ]);
                    } else {
                        // No cities exist - set to null (foreign key allows NULL)
                        Log::warning('No cities found in database, setting city_id to NULL', [
                            'user_id' => $user->id,
                            'application_id' => $dealerApplication->id,
                        ]);
                        $cityId = null;
                    }
                }

                // Prepare studio data
                $studioData = [
                    'dealer_id' => $user->id,
                    'name' => $studioName,
                    'description' => 'Studio for ' . ($dealerApplication->dealership_company_name ?: $user->full_name),
                    'address' => $user->address ?? 'Address not provided',
                    'city_id' => $cityId, // Validated city_id or null
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'is_active' => true,
                    'is_verified' => true,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'settings' => [
                        'auto_assign_listings' => true,
                        'auto_featured' => true,
                        'is_default' => true,
                    ],
                    'business_hours' => [
                        'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                        'tuesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                        'wednesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                        'thursday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                        'friday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
                        'saturday' => ['is_open' => true, 'open' => '09:00', 'close' => '17:00'],
                        'sunday' => ['is_open' => false, 'open' => '09:00', 'close' => '17:00'],
                    ],
                    'amenities' => ['Showroom', 'Parking', 'Customer Service'],
                    'specialties' => ['All Vehicle Types'],
                ];

                // Add odoo_id if column exists
                if (\Illuminate\Support\Facades\Schema::hasColumn('dealer_studios', 'odoo_id')) {
                    $studioData['odoo_id'] = $odooId;
                }

                // Create studio
                $studio = \App\Models\DealerStudio::create($studioData);

                // Verify creation
                if (!$studio || !$studio->id) {
                    throw new \Exception('Studio creation failed - studio object is invalid after creation');
                }

                // Associate existing listings with studio
                if (\Illuminate\Support\Facades\Schema::hasColumn('car_listings', 'studio_id')) {
                    $associatedCount = \App\Models\CarListing::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->whereNull('studio_id')
                        ->update(['studio_id' => $studio->id]);

                    Log::info('Associated existing listings with studio', [
                        'studio_id' => $studio->id,
                        'associated_count' => $associatedCount,
                    ]);
                }

                // Auto-promote dealer's listings to featured
                $promotedCount = \App\Models\CarListing::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('is_featured', false)
                    ->update([
                        'is_featured' => true,
                        'featured_at' => now(),
                    ]);

                Log::info('Promoted dealer listings to featured', [
                    'studio_id' => $studio->id,
                    'promoted_count' => $promotedCount,
                ]);

                return $studio;
            });

            Log::info('Default studio created successfully for approved dealer', [
                'odoo_id' => $odooId,
                'dealer_id' => $user->id,
                'studio_id' => $studio->id,
                'studio_name' => $studio->name,
                'city_id' => $studio->city_id,
                'is_verified' => $studio->is_verified,
                'verification_status' => $studio->verification_status,
            ]);

            return $studio;

        } catch (\Exception $e) {
            Log::error('Failed to create studio for approved dealer', [
                'odoo_id' => $odooId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to ensure caller knows about the failure
            throw new OdooServiceException('Failed to create studio for approved dealer: ' . $e->getMessage());
        }
    }

    /**
     * Legacy method - kept for backward compatibility
     * Now delegates to createStudioForApprovedDealer using odoo_id
     * 
     * @deprecated Use createStudioForApprovedDealer() instead
     */
    protected function createDefaultStudioForDealer(User $user): void
    {
        // Get the latest dealer application to find odoo_id
        $dealerApplication = $user->latestDealerApplication;

        if (!$dealerApplication || !$dealerApplication->odoo_id) {
            Log::warning('Cannot create studio - dealer application missing or has no odoo_id', [
                'user_id' => $user->id,
                'has_application' => $dealerApplication ? true : false,
                'has_odoo_id' => $dealerApplication && $dealerApplication->odoo_id ? true : false,
            ]);
            throw new \Exception('Cannot create studio: dealer application missing or has no odoo_id');
        }

        // Use the robust method
        $this->createStudioForApprovedDealer($dealerApplication->odoo_id);
    }

    /**
     * Complete payment for dealer application and create subscription
     * This method handles the full payment completion flow including subscription creation
     * Can be called from approval flow or payment completion endpoint
     * 
     * @param DealerApplication $application
     * @param array $paymentDetails Additional payment details to store
     * @return UserSubscription|null
     */
    public function completePaymentForApplication(DealerApplication $application, array $paymentDetails = []): ?UserSubscription
    {
        // Validate application has a user
        if (!$application->user) {
            Log::error('Dealer application has no associated user', [
                'application_id' => $application->id,
            ]);
            return null;
        }

        // Skip if payment already completed
        if ($application->payment_completed) {
            Log::info('Payment already completed for dealer application', [
                'application_id' => $application->id,
            ]);
            
            // Return existing subscription if it exists
            return UserSubscription::where('user_id', $application->user->id)
                ->where('subscription_plan_id', $application->subscription_plan_id)
                ->where('status', 'active')
                ->first();
        }

        // Complete payment on the application model
        $application->completePayment();

        // Check if subscription already exists
        $userSubscription = UserSubscription::where('user_id', $application->user->id)
            ->where('subscription_plan_id', $application->subscription_plan_id)
            ->where('status', 'active')
            ->first();

        if ($userSubscription) {
            // Update existing subscription with payment details
            $userSubscription->update([
                'payment_details' => array_merge(
                    $userSubscription->payment_details ?? [],
                    array_merge($paymentDetails, [
                        'dealer_application_id' => $application->id,
                        'payment_completed_at' => now()->toISOString(),
                    ])
                ),
            ]);

            Log::info('Updated existing subscription with payment details', [
                'subscription_id' => $userSubscription->id,
                'application_id' => $application->id,
            ]);

            return $userSubscription;
        }

        // Create new subscription if it doesn't exist
        if ($application->subscription_plan_id) {
            $plan = SubscriptionPlan::find($application->subscription_plan_id);
            
            if ($plan) {
                // Cancel any existing active subscription for this user
                $existingSubscription = UserSubscription::where('user_id', $application->user->id)
                    ->where('status', 'active')
                    ->first();
                
                if ($existingSubscription) {
                    $existingSubscription->cancel();
                }

                // Use expires_at from application if set, otherwise calculate from plan
                $expiresAt = $application->expires_at ?? $plan->calculateExpirationDate();

                // Create new subscription record
                $userSubscription = UserSubscription::create([
                    'user_id' => $application->user->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'active',
                    'started_at' => Carbon::now(),
                    'expires_at' => $expiresAt,
                    'grace_period_days' => 15, // Default 15-day grace period
                    'payment_details' => array_merge($paymentDetails, [
                        'dealer_application_id' => $application->id,
                    ]),
                ]);

                Log::info('User subscription created for dealer application', [
                    'user_id' => $application->user->id,
                    'subscription_plan_id' => $plan->id,
                    'application_id' => $application->id,
                    'subscription_id' => $userSubscription->id,
                ]);

                return $userSubscription;
            } else {
                Log::warning('Subscription plan not found when completing payment', [
                    'application_id' => $application->id,
                    'subscription_plan_id' => $application->subscription_plan_id,
                ]);
            }
        } else {
            Log::warning('Dealer application has no subscription_plan_id when completing payment', [
                'application_id' => $application->id,
            ]);
        }

        return null;
    }
}
