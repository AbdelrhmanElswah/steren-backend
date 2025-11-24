<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfilePictureRequest;
use App\Http\Requests\Auth\SetFavoritesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Media;
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
            
            return $this->successResponse([
                'user' => new UserResource($user),
                'dealer_status' => $dealerStatus,
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
