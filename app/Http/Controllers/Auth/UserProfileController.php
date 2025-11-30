<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfilePictureRequest;
use App\Http\Requests\Auth\UpdateProfileCompleteRequest;
use App\Http\Requests\Auth\SetFavoritesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Media;
use App\Models\AuthorizedAgency;
use App\Models\DealerApplication;
use App\Services\OdooService;
use App\Traits\ApiResponse;
use App\Traits\HandlesFileUpload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    use ApiResponse, HandlesFileUpload;

    public function __construct(
        private OdooService $odooService
    ) {}

    /**
     * Update user profile information
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            DB::beginTransaction();

            // Update user information
            $user->update([
                'full_name' => $validated['full_name'] ?? $user->full_name,
                'email' => $validated['email'] ?? $user->email,
                'phone' => $validated['phone'] ?? $user->phone,
                'birthday' => $validated['birthday'] ?? $user->birthday,
                'notifications_enabled' => $validated['notifications_enabled'] ?? $user->notifications_enabled,
                'marketing_emails_enabled' => $validated['marketing_emails_enabled'] ?? $user->marketing_emails_enabled,
            ]);

            // Update notification preferences if provided
            if (isset($validated['notification_preferences'])) {
                $user->update([
                    'notification_preferences' => $validated['notification_preferences']
                ]);
            }

            // Update language if provided
            if (isset($validated['language_id'])) {
                $user->update([
                    'language_id' => $validated['language_id']
                ]);
            }

            DB::commit();

            // Sync to Odoo if user has partner_id/odoo_id
            $this->syncToOdoo($user, $validated);

            Log::info('User profile updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($validated)
            ]);

            return $this->successResponse([
                'user' => new UserResource($user),
            ], 'Profile updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user profile', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse('Current password is incorrect', 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            Log::info('User password updated successfully', [
                'user_id' => $user->id
            ]);

            return $this->successResponse(null, 'Password updated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to update user password', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to update password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user profile picture
     */
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $file = $request->file('profile_picture');

            DB::beginTransaction();

            // Delete old profile picture/media if it exists
            $this->deleteOldProfilePicture($user);

            // Upload new profile picture to S3
            $uploadResult = $this->uploadFileToS3($file, $user, 'profile-pictures');

            // Create media record
            $media = Media::create([
                'media' => $uploadResult['file_name'],
                'path_file_name' => $uploadResult['path'],
                'file_type' => $uploadResult['file_type'],
                'modelable_id' => $user->id,
                'modelable_type' => get_class($user),
                'info' => 'profile_picture',
                'order' => 0
            ]);

            DB::commit();

            // Sync profile picture to Odoo
            $this->syncProfilePictureToOdoo($user, $media->public_url ?? $uploadResult['url']);

            Log::info('User profile picture updated successfully', [
                'user_id' => $user->id,
                'file_name' => $uploadResult['file_name']
            ]);

            return $this->successResponse([
                'profile_picture' => $media->public_url ?? $uploadResult['url'],
            ], 'Profile picture updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user profile picture', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to update profile picture: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user profile information and picture in one request
     */
    public function updateProfileComplete(UpdateProfileCompleteRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validated();
            $hasProfilePicture = $request->hasFile('profile_picture');

            // Log what we received for debugging
            Log::info('Profile complete update request received', [
                'user_id' => $user->id,
                'validated_keys' => array_keys($validated),
                'validated_data' => $validated,
                'has_profile_picture' => $hasProfilePicture,
                'request_all' => $request->all(),
                'request_has_full_name' => $request->has('full_name'),
                'request_has_email' => $request->has('email'),
                'request_has_phone' => $request->has('phone'),
                'request_has_birthday' => $request->has('birthday'),
                'request_input_full_name' => $request->input('full_name'),
                'request_input_email' => $request->input('email'),
                'content_type' => $request->header('Content-Type'),
            ]);

            DB::beginTransaction();

            // FIRST: Validate password BEFORE updating anything else
            // Check if password update is requested (all 3 fields must be in validated for this to work)
            if (isset($validated['new_password']) && isset($validated['current_password']) && isset($validated['new_password_confirmation'])) {
                // All 3 password fields are present - verify current password BEFORE any updates
                // Check if user has a password set
                if (!$user->password) {
                    DB::rollBack();
                    Log::warning('Password update failed - user has no password set', [
                        'user_id' => $user->id
                    ]);
                    return $this->errorResponse('No password is set for this account. Please use password reset instead.', 400);
                }
                
                // Verify current password matches - THIS MUST HAPPEN BEFORE ANY OTHER UPDATES
                if (!Hash::check($validated['current_password'], $user->password)) {
                    DB::rollBack();
                    Log::warning('Password update failed - current password incorrect', [
                        'user_id' => $user->id,
                        'password_check_failed' => true,
                        'has_user_password' => !empty($user->password),
                        'password_field_sent' => true
                    ]);
                    return $this->errorResponse('Current password is incorrect', 400);
                }
                
                Log::info('Password validation passed, proceeding with updates', [
                    'user_id' => $user->id
                ]);
            }

            // Update user information (if provided)
            // If a field is in $validated, it means it was sent AND passed validation
            // Use array_key_exists to check presence, not isset (which returns false for null values)
            $profileData = [];
            
            // Check if field exists in validated array (was sent and passed validation)
            if (array_key_exists('full_name', $validated)) {
                $profileData['full_name'] = $validated['full_name'];
            }
            
            if (array_key_exists('email', $validated)) {
                $profileData['email'] = $validated['email'];
            }
            
            if (array_key_exists('phone', $validated)) {
                $profileData['phone'] = $validated['phone'];
            }
            
            if (array_key_exists('birthday', $validated)) {
                $profileData['birthday'] = $validated['birthday'];
            }
            
            if (array_key_exists('notifications_enabled', $validated)) {
                $profileData['notifications_enabled'] = $validated['notifications_enabled'];
            }
            
            if (array_key_exists('marketing_emails_enabled', $validated)) {
                $profileData['marketing_emails_enabled'] = $validated['marketing_emails_enabled'];
            }

            if (!empty($profileData)) {
                Log::info('Updating user profile data', [
                    'user_id' => $user->id,
                    'profile_data' => $profileData,
                    'validated_keys' => array_keys($validated),
                    'request_input' => $request->only(['full_name', 'email', 'phone', 'birthday'])
                ]);
                
                $user->update($profileData);
                
                // Verify update succeeded
                $user->refresh();
                Log::info('User profile updated - verification', [
                    'user_id' => $user->id,
                    'updated_email' => $user->email,
                    'updated_phone' => $user->phone,
                    'updated_birthday' => $user->birthday,
                ]);
            } else {
                Log::warning('No profile data to update', [
                    'user_id' => $user->id,
                    'validated_keys' => array_keys($validated),
                    'validated_data' => $validated,
                    'request_input' => $request->only(['full_name', 'email', 'phone', 'birthday']),
                    'request_all' => $request->all()
                ]);
            }

            // Update notification preferences if provided
            if (isset($validated['notification_preferences'])) {
                $user->update([
                    'notification_preferences' => $validated['notification_preferences']
                ]);
            }

            // Update language if provided
            if (isset($validated['language_id'])) {
                $user->update([
                    'language_id' => $validated['language_id']
                ]);
            }

            // Update password if provided (validation already done above)
            if (isset($validated['new_password'])) {
                // Password was already validated above, now update it
                $user->update([
                    'password' => Hash::make($validated['new_password'])
                ]);

                Log::info('User password updated via profile complete', [
                    'user_id' => $user->id
                ]);
            }

            // Handle profile picture update if provided
            $profilePictureUrl = null;
            if ($hasProfilePicture) {
                $file = $request->file('profile_picture');
                
                // Delete old profile picture/media if it exists
                $this->deleteOldProfilePicture($user);

                // Upload new profile picture to S3
                $uploadResult = $this->uploadFileToS3($file, $user, 'profile-pictures');

                // Create media record
                $media = Media::create([
                    'media' => $uploadResult['file_name'],
                    'path_file_name' => $uploadResult['path'],
                    'file_type' => $uploadResult['file_type'],
                    'modelable_id' => $user->id,
                    'modelable_type' => get_class($user),
                    'info' => 'profile_picture',
                    'order' => 0
                ]);

                $profilePictureUrl = $media->public_url ?? $uploadResult['url'];
            }

            DB::commit();

            // Sync to Odoo if user has partner_id/odoo_id
            // Prepare data for Odoo sync
            $odooData = [];
            
            // Add profile fields that were updated
            if (isset($validated['full_name'])) {
                $odooData['full_name'] = $validated['full_name'];
            }
            if (isset($validated['phone'])) {
                $odooData['phone'] = $validated['phone'];
            }
            if (isset($validated['email'])) {
                $odooData['email'] = $validated['email'];
            }
            if (isset($validated['birthday'])) {
                // Map birthday to birth_date in YYYY-MM-DD format
                $birthday = $validated['birthday'];
                if ($birthday instanceof \Carbon\Carbon || $birthday instanceof \DateTime) {
                    $odooData['birth_date'] = $birthday->format('Y-m-d');
                } elseif (is_string($birthday)) {
                    try {
                        $parsedDate = \Carbon\Carbon::parse($birthday);
                        $odooData['birth_date'] = $parsedDate->format('Y-m-d');
                    } catch (\Exception $e) {
                        $odooData['birth_date'] = $birthday;
                    }
                } else {
                    $odooData['birth_date'] = $birthday;
                }
            }

            // Add profile picture URL if updated
            if ($hasProfilePicture && $profilePictureUrl) {
                $odooData['profile_img'] = $profilePictureUrl;
            }

            // Sync to Odoo if there's data to sync
            if (!empty($odooData) && ($user->partner_id || $user->odoo_id)) {
                $clientId = $user->partner_id ?: $user->odoo_id;
                if ($clientId) {
                    $result = $this->odooService->updateClient((int) $clientId, $odooData);
                    
                    if ($result['success']) {
                        Log::info('User profile and picture synced to Odoo successfully', [
                            'user_id' => $user->id,
                            'client_id' => $clientId,
                            'synced_fields' => array_keys($odooData)
                        ]);
                    } else {
                        Log::warning('Failed to sync user profile to Odoo', [
                            'user_id' => $user->id,
                            'client_id' => $clientId,
                            'error' => $result['message'] ?? 'Unknown error'
                        ]);
                    }
                }
            }

            // Refresh user to get latest data
            $user->refresh();
            $user->load('profilePictureMedia');

            Log::info('User profile and picture updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($validated),
                'profile_picture_updated' => $hasProfilePicture
            ]);

            $responseData = [
                'user' => new UserResource($user),
            ];

            if ($hasProfilePicture && $profilePictureUrl) {
                $responseData['profile_picture'] = $profilePictureUrl;
            }

            return $this->successResponse($responseData, 'Profile updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user profile and picture', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'notifications_enabled' => 'sometimes|boolean',
                'marketing_emails_enabled' => 'sometimes|boolean',
                'notification_preferences' => 'sometimes|array',
                'language_id' => 'sometimes|exists:languages,id',
                'theme' => 'sometimes|string|in:light,dark,system',
            ]);

            $user->update($validated);

            Log::info('User preferences updated successfully', [
                'user_id' => $user->id,
                'updated_preferences' => array_keys($validated)
            ]);

            return $this->successResponse([
                'user' => new UserResource($user),
            ], 'Preferences updated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to update user preferences', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to update preferences: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user favorite preferences (brands, models, cities)
     */
    public function getFavorites(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Get favorite brands, models, body types, and cities with full details
            $favoriteBrands = $user->favorite_brands ?? collect([]);
            $favoriteModels = $user->favorite_models ?? collect([]);
            $favoriteBodyTypes = $user->favorite_body_types ?? collect([]);
            $favoriteCities = $user->favorite_cities ?? collect([]);
            
            return $this->successResponse([
                'favorites' => [
                    'brand_ids' => $user->fav_brand_ids ?? [],
                    'model_ids' => $user->fav_models_ids ?? [],
                    'body_type_ids' => $user->fav_body_type_ids ?? [],
                    'city_ids' => $user->fav_citie_ids ?? [],
                    'brands' => $favoriteBrands->map(function($brand) {
                        return [
                            'id' => $brand->id,
                            'name' => $brand->name,
                        ];
                    })->values(),
                    'models' => $favoriteModels->map(function($model) {
                        return [
                            'id' => $model->id,
                            'name' => $model->name,
                            'brand_id' => $model->car_brand_id,
                        ];
                    })->values(),
                    'body_types' => $favoriteBodyTypes->map(function($bodyType) {
                        return [
                            'id' => $bodyType->id,
                            'name' => $bodyType->name,
                        ];
                    })->values(),
                    'cities' => $favoriteCities->map(function($city) {
                        return [
                            'id' => $city->id,
                            'name' => $city->name,
                        ];
                    })->values(),
                ]
            ], 'Favorite preferences retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve favorite preferences', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve favorite preferences: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Set user favorite preferences (brands, models, cities)
     */
    public function setFavorites(SetFavoritesRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            DB::beginTransaction();

            // Update favorite preferences
            $updateData = [];
            
            if (isset($validated['fav_brand_ids'])) {
                $updateData['fav_brand_ids'] = $validated['fav_brand_ids'];
            }
            
            if (isset($validated['fav_models_ids'])) {
                $updateData['fav_models_ids'] = $validated['fav_models_ids'];
            }
            
            if (isset($validated['fav_body_type_ids'])) {
                $updateData['fav_body_type_ids'] = $validated['fav_body_type_ids'];
            }
            
            if (isset($validated['fav_citie_ids'])) {
                $updateData['fav_citie_ids'] = $validated['fav_citie_ids'];
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            DB::commit();

            Log::info('User favorite preferences updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData)
            ]);

            return $this->successResponse([
                'user' => new UserResource($user),
                'favorites' => [
                    'brand_ids' => $user->fav_brand_ids ?? [],
                    'model_ids' => $user->fav_models_ids ?? [],
                    'body_type_ids' => $user->fav_body_type_ids ?? [],
                    'city_ids' => $user->fav_citie_ids ?? [],
                ]
            ], 'Favorite preferences updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update favorite preferences', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to update favorite preferences: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user profile information
     */
    public function getProfile(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Load dealer application relationships
            $user->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan', 'activeDealerApplication']);
            
            // Build dealer status data (similar to /dealer/status endpoint)
            $latestApplication = $user->latestDealerApplication;
            $activeApplication = $user->activeDealerApplication;
            
            $dealerStatus = [
                'has_application' => $latestApplication !== null,
            ];
            
            if ($latestApplication) {
                $dealerStatus['application'] = [
                    'id' => $latestApplication->id,
                    'status' => $latestApplication->status?->name,
                    'status_badge_color' => $latestApplication->status_badge_color,
                    'seller_type' => $latestApplication->seller_type,
                    'dealership_company_name' => $latestApplication->dealership_company_name,
                    'subscription_plan' => $latestApplication->subscriptionPlan ? [
                        'id' => $latestApplication->subscriptionPlan->id,
                        'name' => $latestApplication->subscriptionPlan->name,
                        'price' => $latestApplication->subscriptionPlan->price,
                        'formatted_price' => $latestApplication->subscriptionPlan->formatted_price,
                    ] : null,
                    'payment_completed' => $latestApplication->payment_completed,
                    'created_at' => $latestApplication->created_at,
                    'reviewed_at' => $latestApplication->reviewed_at,
                    'approved_at' => $latestApplication->approved_at,
                    'expires_at' => $latestApplication->expires_at,
                    'rejection_reason' => $latestApplication->rejection_reason,
                    'days_until_expiration' => $latestApplication->days_until_expiration,
                ];
                $dealerStatus['is_active'] = $activeApplication ? $activeApplication->isActive() : false;
                $dealerStatus['is_expiring_soon'] = $activeApplication ? $activeApplication->isExpiringSoon() : false;
                $dealerStatus['is_expired'] = $latestApplication->isExpired();
                
                // Add specific messages based on status
                switch ($latestApplication->status?->name) {
                    case 'pending':
                        $dealerStatus['message'] = 'Your application is pending review';
                        break;
                    case 'under_review':
                        $dealerStatus['message'] = 'Your application is under review';
                        break;
                    case 'approved':
                        if ($latestApplication->payment_completed) {
                            if ($activeApplication && $activeApplication->isActive()) {
                                $dealerStatus['message'] = 'You are now a verified dealer';
                            } elseif ($latestApplication->isExpired()) {
                                $dealerStatus['message'] = 'Your subscription has expired';
                            } elseif ($activeApplication && $activeApplication->isExpiringSoon()) {
                                $dealerStatus['message'] = 'Your subscription expires in ' . $latestApplication->days_until_expiration . ' days';
                            }
                        } else {
                            $dealerStatus['message'] = 'Your application has been approved. Please complete payment to activate your dealer account';
                        }
                        break;
                    case 'rejected':
                        $dealerStatus['message'] = 'Your application was not approved';
                        break;
                }
            } else {
                $dealerStatus['application'] = null;
                $dealerStatus['is_active'] = false;
                $dealerStatus['is_expiring_soon'] = false;
                $dealerStatus['is_expired'] = false;
                $dealerStatus['message'] = 'No dealer application found';
            }
            
            // Build authorized agency data
            $authorizedAgencie = [
                'is_authorized_agencie' => false,
                'brand' => null,
            ];
            
            if ($user->partner_id) {
                // Check if user is an authorized agency
                $authorizedAgency = AuthorizedAgency::where('partner_id', $user->partner_id)->first();
                
                if ($authorizedAgency) {
                    $authorizedAgencie['is_authorized_agencie'] = true;
                    
                    // Get dealer application by partner_id to find the brand
                    $dealerApplication = DealerApplication::where('partner_id', $user->partner_id)
                        ->with('brand')
                        ->latest()
                        ->first();
                    
                    if ($dealerApplication && $dealerApplication->brand) {
                        $authorizedAgencie['brand'] = [
                            'id' => $dealerApplication->brand->id,
                            'name' => $dealerApplication->brand->name,
                            'odoo_id' => $dealerApplication->brand->odoo_id,
                        ];
                    }
                }
            }
            
            return $this->successResponse([
                'user' => new UserResource($user),
                'dealer_status' => $dealerStatus,
                'authorized_agencie' => $authorizedAgencie,
            ], 'Profile information retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user profile', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync user profile updates to Odoo
     */
    private function syncToOdoo(User $user, array $updatedFields): void
    {
        // Only sync if user has an Odoo client ID
        if (!$user->partner_id && !$user->odoo_id) {
            return;
        }

        try {
            // Get Odoo client ID (prefer partner_id, fallback to odoo_id)
            $clientId = $user->partner_id ?: $user->odoo_id;
            
            if (!$clientId) {
                return;
            }

            // Prepare data for Odoo (only include fields that were updated)
            $odooData = [];

            if (isset($updatedFields['full_name'])) {
                $odooData['full_name'] = $updatedFields['full_name'];
            }

            if (isset($updatedFields['phone'])) {
                $odooData['phone'] = $updatedFields['phone'];
            }

            if (isset($updatedFields['email'])) {
                $odooData['email'] = $updatedFields['email'];
            }

            if (isset($updatedFields['birthday'])) {
                // Map birthday to birth_date in YYYY-MM-DD format
                $birthday = $updatedFields['birthday'];
                if ($birthday instanceof \Carbon\Carbon || $birthday instanceof \DateTime) {
                    $odooData['birth_date'] = $birthday->format('Y-m-d');
                } elseif (is_string($birthday)) {
                    // Try to parse and format the date string
                    try {
                        $parsedDate = \Carbon\Carbon::parse($birthday);
                        $odooData['birth_date'] = $parsedDate->format('Y-m-d');
                    } catch (\Exception $e) {
                        // If parsing fails, use as-is (assuming it's already in YYYY-MM-DD format)
                        $odooData['birth_date'] = $birthday;
                    }
                } else {
                    $odooData['birth_date'] = $birthday;
                }
            }

            // Only call Odoo if there's data to sync
            if (!empty($odooData)) {
                $result = $this->odooService->updateClient((int) $clientId, $odooData);
                
                if ($result['success']) {
                    Log::info('User profile synced to Odoo successfully', [
                        'user_id' => $user->id,
                        'client_id' => $clientId,
                        'synced_fields' => array_keys($odooData)
                    ]);
                } else {
                    Log::warning('Failed to sync user profile to Odoo', [
                        'user_id' => $user->id,
                        'client_id' => $clientId,
                        'error' => $result['message'] ?? 'Unknown error'
                    ]);
                }
            }

        } catch (\Exception $e) {
            // Don't fail the request if Odoo sync fails, just log it
            Log::error('Error syncing user profile to Odoo', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sync profile picture to Odoo
     */
    private function syncProfilePictureToOdoo(User $user, string $profilePictureUrl): void
    {
        // Only sync if user has an Odoo client ID
        if (!$user->partner_id && !$user->odoo_id) {
            return;
        }

        try {
            // Get Odoo client ID (prefer partner_id, fallback to odoo_id)
            $clientId = $user->partner_id ?: $user->odoo_id;
            
            if (!$clientId) {
                return;
            }

            $result = $this->odooService->updateClient((int) $clientId, [
                'profile_img' => $profilePictureUrl
            ]);
            
            if ($result['success']) {
                Log::info('Profile picture synced to Odoo successfully', [
                    'user_id' => $user->id,
                    'client_id' => $clientId
                ]);
            } else {
                Log::warning('Failed to sync profile picture to Odoo', [
                    'user_id' => $user->id,
                    'client_id' => $clientId,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            // Don't fail the request if Odoo sync fails, just log it
            Log::error('Error syncing profile picture to Odoo', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete old profile picture from S3
     */
    private function deleteOldProfilePicture(User $user): void
    {
        try {
            // Get existing profile picture media
            $existingMedia = Media::where('modelable_id', $user->id)
                ->where('modelable_type', get_class($user))
                ->where('info', 'profile_picture')
                ->first();

            if ($existingMedia && $existingMedia->path_file_name) {
                // Delete from S3
                Storage::disk('s3')->delete($existingMedia->path_file_name);
            }

            // Delete media record
            Media::where('modelable_id', $user->id)
                ->where('modelable_type', get_class($user))
                ->where('info', 'profile_picture')
                ->delete();

        } catch (\Exception $e) {
            Log::warning('Failed to delete old profile picture', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
