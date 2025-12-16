<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class GoogleAuthController extends Controller
{
    use ApiResponse;
    
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle Google Sign-In from mobile app
     */
    public function handleGoogleSignIn(Request $request)
    {
        $request->validate([
            'token' => 'required|string', // ID token from Google Sign-In
        ]);

        try {
            // Verify the Google token
            $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->token);

            if (!$payload) {
                return $this->errorResponse('Invalid Google token', 401);
            }

            // Extract user information
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? '';
            $googleAvatar = $payload['picture'] ?? null;

            // Find or create user
            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                // Update existing user
                if (!$user->google_id) {
                    $user->google_id = $googleId;
                    $user->save();
                }
                
                // Add Google profile picture if user doesn't have a profile picture
                if ($googleAvatar && !$this->userHasProfilePicture($user)) {
                    $this->saveGoogleProfilePicture($user, $googleAvatar);
                }
            } else {
                // Create new user
                $user = User::create([
                    'google_id' => $googleId,
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => null, // No password for Google users
                ]);

                // Save Google profile picture
                if ($googleAvatar) {
                    $this->saveGoogleProfilePicture($user, $googleAvatar);
                }
            }

            // Generate token using your existing auth service
            $token = $user->createToken('auth_token')->plainTextToken;

            $user->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan']);

            return $this->successResponse([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Logged in with Google');

        } catch (\Exception $e) {
            return $this->errorResponse('Google authentication failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check if user has a profile picture
     */
    private function userHasProfilePicture(User $user): bool
    {
        return Media::where('user_id', $user->id)
            ->where('type', 'profile') // Adjust based on your media type naming
            ->exists();
    }

    /**
     * Save Google profile picture to media table
     */
    private function saveGoogleProfilePicture(User $user, string $googleAvatarUrl): void
    {
        try {
            // Download the image from Google
            $imageContent = file_get_contents($googleAvatarUrl);
            
            if ($imageContent === false) {
                return; // Skip if unable to download
            }

            // Generate unique filename
            $filename = 'profile_' . $user->id . '_' . time() . '.jpg';
            $path = 'profiles/' . $filename;

            // Store the image
            Storage::disk('public')->put($path, $imageContent);

            // Save to media table
            Media::create([
                'user_id' => $user->id,
                'type' => 'profile', // Adjust based on your media type column
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'mime_type' => 'image/jpeg',
                'size' => strlen($imageContent),
                // Add any other fields your media table requires
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail the authentication
            \Log::error('Failed to save Google profile picture: ' . $e->getMessage());
        }
    }

    /**
     * Web redirect to Google (optional, for web testing)
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google callback (optional, for web testing)
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                if (!$user->google_id) {
                    $user->google_id = $googleUser->getId();
                    $user->save();
                }
                
                // Add Google profile picture if user doesn't have one
                if ($googleUser->getAvatar() && !$this->userHasProfilePicture($user)) {
                    $this->saveGoogleProfilePicture($user, $googleUser->getAvatar());
                }
            } else {
                $user = User::create([
                    'google_id' => $googleUser->getId(),
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'email_verified_at' => now(),
                    'password' => null,
                ]);

                // Save Google profile picture
                if ($googleUser->getAvatar()) {
                    $this->saveGoogleProfilePicture($user, $googleUser->getAvatar());
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $user->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan']);

            return response()->json([
                'user' => new UserResource($user),
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}