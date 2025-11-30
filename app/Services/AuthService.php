<?php

namespace App\Services;

use App\Models\User;
use App\Services\OdooService;
use App\Services\ClientService;
use App\Services\FirebaseService;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooPartnerCreationException;
use App\Exceptions\OdooOtpVerificationException;
use App\Traits\OtpHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    use OtpHelper;

    public function __construct(
        private OdooService $odooService,
        private ClientService $clientService,
        private FirebaseService $firebaseService,
        private StandingTechService $standingTechService,
        private UnifiedNotificationService $notificationService
    ) {
    }

    public function register(array $payload): array
    {
        // Check for existing users first (before transaction to avoid locking)
        if (isset($payload['email']) && $payload['email']) {
            $existingUser = User::where('email', $payload['email'])->first();
            
            if ($existingUser) {
                if ($existingUser->is_pending) {
                    throw new \Exception('An account with this email already exists but is pending completion. Please use the complete-pending-account endpoint instead.');
                } else {
                    throw new \Exception('An account with this email already exists. Please use login instead.');
                }
            }
        }
        
        // Check if phone already exists
        if (isset($payload['phone']) && $payload['phone']) {
            $existingPhone = User::where('phone', $payload['phone'])->first();
            
            if ($existingPhone) {
                throw new \Exception('An account with this phone number already exists.');
            }
        }
        
        // Create partner in Odoo FIRST, then create user in Laravel with partner_id
        $partnerId = null;
        $odooId = null;
        
        // Only create Odoo partner if we have required data (full_name, email, phone)
        if (!empty($payload['full_name']) && !empty($payload['email']) && !empty($payload['phone'])) {
            try {
                Log::info('Creating Odoo partner during registration', [
                    'full_name' => $payload['full_name'],
                    'phone' => $payload['phone'],
                    'email' => $payload['email']
                ]);
                
                // Call OdooService directly to create partner without creating user
                $odooResult = $this->odooService->createClient([
                    'full_name' => $payload['full_name'],
                    'phone' => $payload['phone'],
                    'email' => $payload['email']
                ]);
                
                if ($odooResult['success'] && $odooResult['odoo_client_id']) {
                    $partnerId = (string) $odooResult['odoo_client_id'];
                    $odooId = (string) $odooResult['odoo_client_id'];
                    
                    Log::info('Odoo partner created successfully during registration', [
                        'partner_id' => $partnerId,
                        'odoo_id' => $odooId
                    ]);
                } else {
                    throw new OdooPartnerCreationException('Odoo partner creation failed: ' . ($odooResult['message'] ?? 'Unknown error'));
                }
            } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                Log::error('Odoo partner creation failed during registration', [
                    'error' => $e->getMessage(),
                    'payload' => $payload
                ]);
                
                // Don't create user if Odoo partner creation fails
                throw new \Exception('Registration failed: Unable to create partner in Odoo. ' . $e->getMessage());
            }
        } else {
            // Missing required data for Odoo - will create partner during OTP verification
            Log::info('Missing required data for Odoo partner creation during registration. Will create during OTP verification.', [
                'has_full_name' => !empty($payload['full_name']),
                'has_email' => !empty($payload['email']),
                'has_phone' => !empty($payload['phone'])
            ]);
        }
        
        // Create user and related data in transaction
        $result = DB::transaction(function () use ($payload, $partnerId, $odooId) {
            $user = User::query()->create([
                'full_name' => $payload['full_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'password' => isset($payload['password']) ? Hash::make($payload['password']) : null,
                'firebase_uid' => $payload['firebase_uid'] ?? null,
                'partner_id' => $partnerId,
                'odoo_id' => $odooId,
            ]);

            $otp = $this->generateOtp();
            $this->storeOtp($this->phoneKey($user->phone), $otp['code']);

            $token = $user->createToken('otp-verification', ['verify-otp'])->plainTextToken;

            // Create welcome notification (outside transaction to avoid blocking)
            try {
                $this->notificationService->createUserCreatedNotification($user);
            } catch (\Exception $e) {
                Log::warning('Failed to create welcome notification for new user (non-critical)', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                // Don't throw - notification failure shouldn't block registration
            }

            return [
                'user' => $user,
                'otp' => $otp['code'],
                'token' => $token,
            ];
        });

        // Send WhatsApp OTP asynchronously (outside transaction to avoid blocking)
        if ($result['user']->phone && $this->isIraqNumber($result['user']->phone)) {
            try {
                // Use lower timeout to avoid blocking too long
                $this->standingTechService->sendOTPWhatsApp($result['user']->phone, $result['otp'], 'en');
            } catch (\Throwable $e) {
                Log::warning('Failed to send WhatsApp OTP on register', [
                    'user_id' => $result['user']->id,
                    'phone' => $result['user']->phone,
                    'error' => $e->getMessage(),
                ]);
                // Don't throw - OTP is stored, user can request resend if needed
            }
        }

        return [
            'user' => $result['user'],
            'otp_sent' => true,
            'token' => $result['token'],
        ];
    }

    public function login(string $identifier, string $password): array
    {
        $user = User::query()
            ->when(str_contains($identifier, '@'), fn ($q) => $q->where('email', $identifier))
            ->when(!str_contains($identifier, '@'), fn ($q) => $q->orWhere('phone', $identifier))
            ->first();

        if (!$user || !Hash::check($password, (string) $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $token = $user->createToken('api')->plainTextToken;
        $user->updateLastLogin();

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function sendOtp(string $phone): array
    {
        $otp = $this->generateOtp();
        $this->storeOtp($this->phoneKey($phone), $otp['code']);

        // Send OTP via WhatsApp using StandingTech for Iraq numbers only
        if ($this->isIraqNumber($phone)) {
            try {
                $this->standingTechService->sendOTPWhatsApp($phone, $otp['code'], 'en');
            } catch (\Throwable $e) {
                Log::warning('Failed to send WhatsApp OTP', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => true];
    }

    public function verifyOtp(User $user, string $code, ?array $clientData = null): array
    {
        if ($user->phone_verified_at) {
            Log::info('Phone already verified, skipping OTP validation', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'phone_verified_at' => $user->phone_verified_at,
            ]);
            
            $token = $user->createToken('api')->plainTextToken;
            
            return [
                'user' => $user,
                'token' => $token,
                'client_integration' => [
                    'success' => true,
                    'partner_id' => $user->partner_id,
                    'message' => 'Phone already verified'
                ]
            ];
        }

        if (!$this->validateOtp($this->phoneKey($user->phone), $code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code'],
            ]);
        }

        $this->clearOtp($this->phoneKey($user->phone));
        $user->markAsVerified();

        $clientIntegration = [
            'success' => false,
            'partner_id' => null,
            'message' => 'Client creation not attempted'
        ];

        // If user already has partner_id from registration, skip Odoo creation
        if ($user->partner_id) {
            Log::info('User already has partner_id from registration, skipping Odoo client creation during OTP verification', [
                'user_id' => $user->id,
                'partner_id' => $user->partner_id
            ]);
            
            $clientIntegration = [
                'success' => true,
                'partner_id' => $user->partner_id,
                'message' => 'Partner already created during registration'
            ];
        } elseif ($clientData && isset($clientData['full_name'])) {
            try {
                $email = $clientData['email'] ?? $clientData['phone'] . '@steren.app';
                
                Log::info('Creating Odoo client during OTP verification', [
                    'user_id' => $user->id,
                    'full_name' => $clientData['full_name'],
                    'phone' => $clientData['phone'],
                    'email' => $email
                ]);
                
                // Call OdooService directly to create partner without creating user
                $odooResult = $this->odooService->createClient([
                    'full_name' => $clientData['full_name'],
                    'phone' => $clientData['phone'],
                    'email' => $email
                ]);
                
                if ($odooResult['success'] && $odooResult['odoo_client_id']) {
                    $partnerId = (string) $odooResult['odoo_client_id'];
                    $user->update([
                        'partner_id' => $partnerId,
                        'odoo_id' => (string) $odooResult['odoo_client_id']
                    ]);
                    
                    $clientIntegration = [
                        'success' => true,
                        'partner_id' => $partnerId,
                        'message' => 'Partner created successfully'
                    ];
                    
                    Log::info('User updated with Odoo partner_id during OTP verification', [
                        'user_id' => $user->id,
                        'partner_id' => $partnerId,
                        'odoo_id' => $odooResult['odoo_client_id']
                    ]);
                } else {
                    throw new OdooPartnerCreationException('Odoo partner creation failed: ' . ($odooResult['message'] ?? 'Unknown error'));
                }

            } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                Log::error('Odoo client creation failed during OTP verification - deleting user', [
                    'user_id' => $user->id,
                    'phone' => $clientData['phone'],
                    'client_data' => $clientData,
                    'error' => $e->getMessage()
                ]);

                // Delete the user since Odoo creation failed
                $userId = $user->id;
                $user->delete();
                
                Log::info('User deleted due to Odoo client creation failure', [
                    'user_id' => $userId,
                    'phone' => $clientData['phone']
                ]);

                // Re-throw the exception to prevent token creation
                throw new \Exception('User registration failed: Unable to create client in Odoo. ' . $e->getMessage());
            }
        } elseif (!$user->partner_id) {
            // User doesn't have partner_id and no clientData - try to create with available user data
            Log::info('User missing partner_id and clientData, attempting Odoo client creation with available data', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'full_name' => $user->full_name
            ]);
            
            try {
                $email = $user->email ?? $user->phone . '@steren.app';
                $fullName = $user->full_name ?? 'User ' . $user->id;
                
                // Call OdooService directly to create partner without creating user
                $odooResult = $this->odooService->createClient([
                    'full_name' => $fullName,
                    'phone' => $user->phone,
                    'email' => $email
                ]);
                
                if ($odooResult['success'] && $odooResult['odoo_client_id']) {
                    $partnerId = (string) $odooResult['odoo_client_id'];
                    $user->update([
                        'partner_id' => $partnerId,
                        'odoo_id' => (string) $odooResult['odoo_client_id']
                    ]);
                    
                    $clientIntegration = [
                        'success' => true,
                        'partner_id' => $partnerId,
                        'message' => 'Partner created successfully'
                    ];
                    
                    Log::info('User updated with Odoo partner_id (no clientData case)', [
                        'user_id' => $user->id,
                        'partner_id' => $partnerId,
                        'odoo_id' => $odooResult['odoo_client_id']
                    ]);
                } else {
                    throw new OdooPartnerCreationException('Odoo partner creation failed: ' . ($odooResult['message'] ?? 'Unknown error'));
                }
                
            } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                Log::error('Odoo client creation failed during OTP verification (no clientData case) - deleting user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                // Delete the user since Odoo creation failed
                $userId = $user->id;
                $user->delete();
                
                Log::info('User deleted due to Odoo client creation failure (no clientData case)', [
                    'user_id' => $userId
                ]);

                // Re-throw the exception to prevent token creation
                throw new \Exception('User registration failed: Unable to create client in Odoo. ' . $e->getMessage());
            }
        }

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'client_integration' => $clientIntegration
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    public function startPasswordReset(string $phone): array
    {
        $user = User::query()->where('phone', $phone)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is not registered in our system.'],
            ]);
        }

        $otp = $this->generateOtp();
        // Store OTP with password reset prefix to distinguish from registration OTP
        $this->storeOtp($this->passwordResetPhoneKey($phone), $otp['code']);

        // Send OTP via WhatsApp using StandingTech for Iraq numbers only
        if ($this->isIraqNumber($phone)) {
            try {
                $this->standingTechService->sendOTPWhatsApp($phone, $otp['code'], 'en');
            } catch (\Throwable $e) {
                Log::warning('Failed to send WhatsApp OTP for password reset', [
                    'user_id' => $user->id,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // TODO: Remove OTP from response in production - this is for testing only
        return [
            'sent' => true,
            'otp' => $otp['code'], // Testing only - remove in production
        ];
    }

    public function verifyPasswordResetOtp(string $phone, string $code): array
    {
        $user = User::query()->where('phone', $phone)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is not registered in our system.'],
            ]);
        }

        // TODO: Remove test bypass code - for testing only
        // Allow universal test/bypass code "1234"
        $isTestCode = (string) $code === '1234';
        $isValidOtp = $this->validateOtp($this->passwordResetPhoneKey($phone), $code);

        if (!$isTestCode && !$isValidOtp) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code'],
            ]);
        }

        // Clear the OTP and mark phone as verified for password reset
        $this->clearOtp($this->passwordResetPhoneKey($phone));
        $this->markPasswordResetVerified($phone);

        return ['verified' => true];
    }

    public function completePasswordReset(string $phone, string $newPassword): array
    {
        $user = User::query()->where('phone', $phone)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is not registered in our system.'],
            ]);
        }

        // Check if password reset OTP was verified
        if (!$this->isPasswordResetVerified($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Please verify the OTP code first before resetting your password.'],
            ]);
        }

        $newPasswordHash = Hash::make($newPassword);

        // Update password in a transaction to ensure it's persisted
        DB::transaction(function () use ($user, $newPasswordHash) {
            $user->password = $newPasswordHash;
            $user->save();
        });

        // Refresh user to get latest data from database
        $user->refresh();

        // Verify the password was actually updated by checking if new password works
        if (!Hash::check($newPassword, $user->password)) {
            Log::error('Password verification failed after reset', [
                'user_id' => $user->id,
                'phone' => $phone,
            ]);
            throw new \Exception('Password update verification failed. Please try again.');
        }

        // Clear the password reset verification flag
        $this->clearPasswordResetVerification($phone);

        return ['updated' => true, 'user' => $user];
    }

    public function socialAuth(string $provider, string $firebaseToken, array $additionalData = []): array
    {
        try {
            $firebasePayload = $this->firebaseService->verifyIdToken($firebaseToken);
            $firebaseUserInfo = $this->firebaseService->extractUserInfo($firebasePayload);
            
            Log::info('Firebase token verified successfully', [
                'provider' => $provider,
                'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                'email' => $firebaseUserInfo['email']
            ]);
            
            $user = User::where('firebase_uid', $firebaseUserInfo['firebase_uid'])->first();
            
            if (!$user && $firebaseUserInfo['email']) {
                $user = User::where('email', $firebaseUserInfo['email'])->first();
                
                if ($user) {
                    $user->update([
                        'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                        'social_provider' => $provider,
                        'firebase_claims' => json_encode($firebaseUserInfo['firebase_claims']),
                    ]);
                }
            }
            
            if (!$user) {
                $userData = [
                    'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                    'email' => $firebaseUserInfo['email'] ?? $additionalData['email'] ?? null,
                    'full_name' => null, // Will be set later
                    'phone' => 'PENDING_' . $firebaseUserInfo['firebase_uid'], // Temporary phone for pending accounts
                    'social_provider' => $provider,
                    'firebase_claims' => json_encode($firebaseUserInfo['firebase_claims']),
                    'is_pending' => true, // Mark as pending account
                ];
                
                $user = User::create($userData);
                
                Log::info('Pending social account created', [
                    'user_id' => $user->id,
                    'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                    'email' => $userData['email']
                ]);
                
                $token = $user->createToken('social-auth-pending')->plainTextToken;
                
                return [
                    'user' => $user,
                    'token' => $token,
                    'is_pending' => true,
                    'message' => 'Account created but requires completion. Please provide your name and phone number.',
                    'firebase_user_info' => $firebaseUserInfo,
                ];
            }
            
            $userData = [
                'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                'full_name' => $firebaseUserInfo['name'] ?? $additionalData['full_name'] ?? $user->full_name,
                'phone' => $firebaseUserInfo['phone_number'] ?? $additionalData['phone'] ?? $user->phone,
                'phone_verified_at' => now(),
                'social_provider' => $provider,
                'firebase_claims' => json_encode($firebaseUserInfo['firebase_claims']),
            ];
            
            if ($firebaseUserInfo['email'] && $user->email !== $firebaseUserInfo['email']) {
                $userData['email'] = $firebaseUserInfo['email'];
            }
            
            // Update the existing user with the new data
            $user->update($userData);
            
            $clientIntegration = [
                'success' => false,
                'partner_id' => null,
                'message' => 'Client creation not attempted'
            ];
            
            // Only create Odoo client if user doesn't already have a partner_id
            if (!$user->partner_id && $userData['full_name'] && ($userData['email'] ?? $user->email)) {
                try {
                    $clientResult = $this->clientService->createClient([
                        'full_name' => $userData['full_name'],
                        'phone' => $userData['phone'] ?? $user->phone ?? 'SOCIAL_' . $firebaseUserInfo['firebase_uid'],
                        'email' => $userData['email'] ?? $user->email
                    ]);
                    
                    $clientIntegration = [
                        'success' => $clientResult['success'],
                        'partner_id' => $clientResult['partner_id'],
                        'message' => $clientResult['message']
                    ];
                    
                    // User is already updated in ClientService with partner_id and odoo_id
                    $user->refresh();
                    
                } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                    Log::error('Client creation failed during social authentication - deleting user', [
                        'user_id' => $user->id,
                        'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                        'provider' => $provider,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Delete the user since Odoo creation failed (we only enter this block if user doesn't have partner_id)
                    $userId = $user->id;
                    $user->delete();
                    
                    Log::info('User deleted due to Odoo client creation failure during social auth', [
                        'user_id' => $userId,
                        'firebase_uid' => $firebaseUserInfo['firebase_uid']
                    ]);
                    
                    // Re-throw the exception to prevent token creation
                    throw new AuthenticationException('Social authentication failed: Unable to create client in Odoo. ' . $e->getMessage());
                }
            } elseif (!$user->partner_id) {
                // User doesn't have partner_id and we can't create Odoo client due to missing data
                Log::error('Social authentication cannot create Odoo client - missing required data', [
                    'user_id' => $user->id,
                    'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                    'provider' => $provider,
                    'has_full_name' => !empty($userData['full_name']),
                    'has_email' => !empty($userData['email'] ?? $user->email)
                ]);
                
                // Delete the user since we can't create Odoo client
                $userId = $user->id;
                $user->delete();
                
                throw new AuthenticationException('Social authentication failed: Missing required information to create client in Odoo.');
            } else {
                // User already has partner_id, use it
                $clientIntegration = [
                    'success' => true,
                    'partner_id' => $user->partner_id,
                    'message' => 'User already has partner_id'
                ];
            }
            
            $token = $user->createToken('social-auth')->plainTextToken;
            
            Log::info('Social authentication successful', [
                'user_id' => $user->id,
                'provider' => $provider,
                'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                'client_integration_success' => $clientIntegration['success']
            ]);
            
            return [
                'user' => $user,
                'token' => $token,
                'is_pending' => false,
                'client_integration' => $clientIntegration,
                'firebase_user_info' => $firebaseUserInfo,
            ];
            
        } catch (\Exception $e) {
            Log::error('Social authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new AuthenticationException('Social authentication failed: ' . $e->getMessage());
        }
    }

    public function completePendingAccount(int $userId, string $fullName, string $phone): array
    {
        try {
            $user = User::findOrFail($userId);
            
            if (!$user->is_pending) {
                throw new \Exception('Account is not pending completion');
            }

            // Check if phone number is already taken by another user
            $existingPhone = User::where('phone', $phone)
                ->where('id', '!=', $userId)
                ->first();
                
            if ($existingPhone) {
                throw new \Exception('This phone number is already registered with another account.');
            }

            DB::beginTransaction();

            $user->update([
                'full_name' => $fullName,
                'phone' => $phone,
                'is_pending' => false,
                'phone_verified_at' => now(),
            ]);

            // Create Odoo client integration - REQUIRED
            $clientIntegration = [
                'success' => false,
                'partner_id' => null,
                'message' => 'Client creation not attempted'
            ];

            try {
                Log::info('Creating Odoo client for pending account completion', [
                    'user_id' => $user->id,
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'email' => $user->email
                ]);
                
                $clientResult = $this->clientService->createClient([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'email' => $user->email
                ]);
                
                Log::info('Odoo client creation result', [
                    'user_id' => $user->id,
                    'success' => $clientResult['success'],
                    'partner_id' => $clientResult['partner_id'],
                    'message' => $clientResult['message']
                ]);
                
                $clientIntegration = [
                    'success' => $clientResult['success'],
                    'partner_id' => $clientResult['partner_id'],
                    'message' => $clientResult['message']
                ];
                
                // User is already updated in ClientService with partner_id and odoo_id
                $user->refresh();
                
                Log::info('User updated with Odoo partner_id', [
                    'user_id' => $user->id,
                    'partner_id' => $user->partner_id,
                    'odoo_id' => $user->odoo_id
                ]);
                
            } catch (OdooConnectionException|OdooPartnerCreationException $e) {
                Log::error('Client creation failed during pending account completion - rolling back', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                // Rollback the transaction - user update will be reverted
                DB::rollBack();
                
                // Re-throw the exception to prevent account completion
                throw new \Exception('Account completion failed: Unable to create client in Odoo. ' . $e->getMessage());
            }

            DB::commit();

            // Create welcome notification after account completion
            try {
                $this->notificationService->createUserCreatedNotification($user);
            } catch (\Exception $e) {
                Log::warning('Failed to create welcome notification for completed pending account (non-critical)', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('Pending account completed successfully', [
                'user_id' => $user->id,
                'client_integration_success' => $clientIntegration['success']
            ]);

            return [
                'user' => $user,
                'client_integration' => $clientIntegration,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete pending account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to complete account: ' . $e->getMessage());
        }
    }

    protected function phoneKey(?string $phone): string
    {
        return (string) $phone;
    }

    protected function passwordResetPhoneKey(?string $phone): string
    {
        return 'password_reset:' . (string) $phone;
    }

    protected function markPasswordResetVerified(string $phone): void
    {
        Cache::put($this->passwordResetVerifiedCacheKey($phone), true, 600); // 10 minutes
    }

    protected function isPasswordResetVerified(string $phone): bool
    {
        return Cache::get($this->passwordResetVerifiedCacheKey($phone), false);
    }

    protected function clearPasswordResetVerification(string $phone): void
    {
        Cache::forget($this->passwordResetVerifiedCacheKey($phone));
    }

    protected function passwordResetVerifiedCacheKey(string $phone): string
    {
        return 'password_reset_verified:' . $phone;
    }

    private function isIraqNumber(?string $phone): bool
    {
        if (!$phone) {
            return false;
        }
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        return str_starts_with($normalized, '964');
    }

}


