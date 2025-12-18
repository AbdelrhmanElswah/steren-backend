<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;

class FirebaseAuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(config('services.firebase.credentials'));
        $this->firebaseAuth = $factory->createAuth();
    }

    /**
     * Handle Firebase Authentication (Google + Apple)
     */
    public function handleFirebaseAuth(Request $request)
    {
        $request->validate([
            'firebase_token' => 'required|string',
            'provider' => 'required|in:google,apple',
            'display_name' => 'nullable|string',
        ]);

        try {
            // Verify Firebase ID token
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($request->firebase_token);
            
            // Get user data from token
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');
            $emailVerified = $verifiedIdToken->claims()->get('email_verified', false);
            
            // Get provider-specific ID
            $firebaseUser = $verifiedIdToken->claims()->get('firebase');
            $providerId = null;
            
            if (isset($firebaseUser['sign_in_provider'])) {
                if ($firebaseUser['sign_in_provider'] === 'google.com') {
                    $providerId = $verifiedIdToken->claims()->get('user_id');
                } elseif ($firebaseUser['sign_in_provider'] === 'apple.com') {
                    $providerId = $verifiedIdToken->claims()->get('user_id');
                }
            }

            // Get name and picture
            $name = $request->display_name ?? $verifiedIdToken->claims()->get('name');
            $picture = $verifiedIdToken->claims()->get('picture');

            // Find or create user
            $user = $this->findOrCreateUser(
                firebaseUid: $firebaseUid,
                email: $email,
                name: $name,
                picture: $picture,
                provider: $request->provider,
                providerId: $providerId,
                emailVerified: $emailVerified
            );

            // Generate Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            $user->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan']);

            return $this->successResponse([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Logged in successfully');

        } catch (\Exception $e) {
            \Log::error('Firebase Auth Error: ' . $e->getMessage());
            return $this->errorResponse('Authentication failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Find or create user
     */
    private function findOrCreateUser(
        string $firebaseUid,
        ?string $email,
        ?string $name,
        ?string $picture,
        string $provider,
        ?string $providerId,
        bool $emailVerified
    ): User {
        // Try to find existing user by Firebase UID
        $user = User::where('firebase_uid', $firebaseUid)->first();

        if ($user) {
            // Update existing user
            $this->updateUser($user, $providerId, $provider, $picture);
            return $user;
        }

        // Try to find by email
        if ($email) {
            $user = User::where('email', $email)->first();
            
            if ($user) {
                // Link Firebase to existing account
                $user->firebase_uid = $firebaseUid;
                
                if ($provider === 'google' && !$user->google_id) {
                    $user->google_id = $providerId;
                } elseif ($provider === 'apple' && !$user->apple_id) {
                    $user->apple_id = $providerId;
                }
                
                $user->save();
                
                // Add profile picture if needed
                if ($picture && !$this->userHasProfilePicture($user)) {
                    $this->saveProfilePicture($user, $picture);
                }
                
                return $user;
            }
        }

        // Create new user
        $user = User::create([
            'firebase_uid' => $firebaseUid,
            'google_id' => $provider === 'google' ? $providerId : null,
            'apple_id' => $provider === 'apple' ? $providerId : null,
            'name' => $name ?? 'User',
            'email' => $email,
            'email_verified_at' => $emailVerified ? now() : null,
            'password' => null,
            'provider' => $provider,
        ]);

        // Save profile picture
        if ($picture) {
            $this->saveProfilePicture($user, $picture);
        }

        return $user;
    }

    /**
     * Update existing user
     */
    private function updateUser(User $user, ?string $providerId, string $provider, ?string $picture): void
    {
        $updated = false;

        if ($provider === 'google' && !$user->google_id && $providerId) {
            $user->google_id = $providerId;
            $updated = true;
        }

        if ($provider === 'apple' && !$user->apple_id && $providerId) {
            $user->apple_id = $providerId;
            $updated = true;
        }

        if (!$user->provider) {
            $user->provider = $provider;
            $updated = true;
        }

        if ($updated) {
            $user->save();
        }

        // Add profile picture if needed
        if ($picture && !$this->userHasProfilePicture($user)) {
            $this->saveProfilePicture($user, $picture);
        }
    }

    /**
     * Check if user has profile picture
     */
    private function userHasProfilePicture(User $user): bool
    {
        return Media::where('user_id', $user->id)
            ->where('type', 'profile')
            ->exists();
    }

    /**
     * Save profile picture
     */
    private function saveProfilePicture(User $user, string $pictureUrl): void
    {
        try {
            $imageContent = file_get_contents($pictureUrl);
            if ($imageContent === false) return;

            $filename = 'profile_' . $user->id . '_' . time() . '.jpg';
            $path = 'profiles/' . $filename;

            Storage::disk('public')->put($path, $imageContent);

            Media::create([
                'user_id' => $user->id,
                'type' => 'profile',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'mime_type' => 'image/jpeg',
                'size' => strlen($imageContent),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to save profile picture: ' . $e->getMessage());
        }
    }
}