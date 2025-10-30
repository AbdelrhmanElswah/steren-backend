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
        private StandingTechService $standingTechService
    ) {
    }

    public function register(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
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
            
            $user = User::query()->create([
                'full_name' => $payload['full_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'password' => isset($payload['password']) ? Hash::make($payload['password']) : null,
                'firebase_uid' => $payload['firebase_uid'] ?? null,
            ]);

            $otp = $this->generateOtp();
            $this->storeOtp($this->phoneKey($user->phone), $otp['code']);

            if ($user->phone && $this->isIraqNumber($user->phone)) {
                try {
                    $this->standingTechService->sendOTPWhatsApp($user->phone, $otp['code'], 'en');
                } catch (\Throwable $e) {
                    Log::warning('Failed to send WhatsApp OTP on register', [
                        'user_id' => $user->id,
                        'phone' => $user->phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $token = $user->createToken('otp-verification', ['verify-otp'])->plainTextToken;

            return [
                'user' => $user,
                'otp_sent' => true,
                'token' => $token,
            ];
        });
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

        if ($clientData && isset($clientData['full_name'])) {
            try {
                $email = $clientData['email'] ?? $clientData['phone'] . '@steren.app';
                
                Log::info('Creating Odoo client during OTP verification', [
                    'user_id' => $user->id,
                    'full_name' => $clientData['full_name'],
                    'phone' => $clientData['phone'],
                    'email' => $email
                ]);
                
                $clientResult = $this->clientService->createClient([
                    'full_name' => $clientData['full_name'],
                    'phone' => $clientData['phone'],
                    'email' => $email
                ]);
                
                Log::info('Odoo client creation result during OTP verification', [
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

                // Update user with partner_id and odoo_id
                if ($clientResult['success'] && $clientResult['partner_id']) {
                    $user->update([
                        'partner_id' => $clientResult['partner_id'],
                        'odoo_id' => $clientResult['partner_id'] // Set odoo_id to the same value as partner_id
                    ]);
                    
                    Log::info('User updated with Odoo partner_id during OTP verification', [
                        'user_id' => $user->id,
                        'partner_id' => $clientResult['partner_id'],
                        'odoo_id' => $clientResult['partner_id']
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Client creation failed during OTP verification', [
                    'user_id' => $user->id,
                    'phone' => $clientData['phone'],
                    'client_data' => $clientData,
                    'error' => $e->getMessage()
                ]);

                // Set fallback partner_id and odoo_id using user ID
                $fallbackPartnerId = 'PARTNER_' . $user->id;
                $user->update([
                    'partner_id' => $fallbackPartnerId,
                    'odoo_id' => $fallbackPartnerId
                ]);

                $clientIntegration = [
                    'success' => false,
                    'partner_id' => $fallbackPartnerId,
                    'message' => 'Client creation failed: ' . $e->getMessage() . '. Using fallback ID.'
                ];
                
                Log::info('User updated with fallback partner_id during OTP verification', [
                    'user_id' => $user->id,
                    'partner_id' => $fallbackPartnerId,
                    'odoo_id' => $fallbackPartnerId
                ]);
            }
        } else {
            // User doesn't have full_name, but we still want to create Odoo client
            Log::info('User missing full_name, attempting Odoo client creation with available data', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'full_name' => $user->full_name
            ]);
            
            try {
                $email = $user->email ?? $user->phone . '@steren.app';
                $fullName = $user->full_name ?? 'User ' . $user->id;
                
                $clientResult = $this->clientService->createClient([
                    'full_name' => $fullName,
                    'phone' => $user->phone,
                    'email' => $email
                ]);
                
                Log::info('Odoo client creation result for user without full_name', [
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
                
                // Update user with partner_id and odoo_id
                if ($clientResult['success'] && $clientResult['partner_id']) {
                    $user->update([
                        'partner_id' => $clientResult['partner_id'],
                        'odoo_id' => $clientResult['partner_id']
                    ]);
                    
                    Log::info('User updated with Odoo partner_id (no full_name case)', [
                        'user_id' => $user->id,
                        'partner_id' => $clientResult['partner_id'],
                        'odoo_id' => $clientResult['partner_id']
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::error('Client creation failed during OTP verification (no full_name case)', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                // Set fallback partner_id and odoo_id using user ID
                $fallbackPartnerId = 'PARTNER_' . $user->id;
                $user->update([
                    'partner_id' => $fallbackPartnerId,
                    'odoo_id' => $fallbackPartnerId
                ]);

                $clientIntegration = [
                    'success' => false,
                    'partner_id' => $fallbackPartnerId,
                    'message' => 'Client creation failed: ' . $e->getMessage() . '. Using fallback ID.'
                ];
                
                Log::info('User updated with fallback partner_id (no full_name case)', [
                    'user_id' => $user->id,
                    'partner_id' => $fallbackPartnerId,
                    'odoo_id' => $fallbackPartnerId
                ]);
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
        $user = User::query()->where('phone', $phone)->firstOrFail();
        $otp = $this->generateOtp();
        $this->storeOtp($this->phoneKey($phone), $otp['code']);

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

        return ['sent' => true, 'user' => $user];
    }

    public function completePasswordReset(string $phone, string $code, string $newPassword): array
    {
        if (!$this->validateOtp($this->phoneKey($phone), $code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code'],
            ]);
        }
        $this->clearOtp($this->phoneKey($phone));

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $user->update(['password' => Hash::make($newPassword)]);

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
            
            if ($userData['full_name'] && ($userData['email'] ?? $user->email)) {
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
                    
                    $user->update(['partner_id' => $clientResult['partner_id']]);
                    
                } catch (\Exception $e) {
                    Log::error('Client creation failed during social authentication', [
                        'user_id' => $user->id,
                        'firebase_uid' => $firebaseUserInfo['firebase_uid'],
                        'provider' => $provider,
                        'error' => $e->getMessage()
                    ]);
                    
                    $clientIntegration = [
                        'success' => false,
                        'partner_id' => null,
                        'message' => 'Client creation failed: ' . $e->getMessage()
                    ];
                }
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

            // Create Odoo client integration
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
                
                $user->update([
                    'partner_id' => $clientResult['partner_id'],
                    'odoo_id' => $clientResult['partner_id'] 
                ]);
                
                Log::info('User updated with Odoo partner_id', [
                    'user_id' => $user->id,
                    'partner_id' => $clientResult['partner_id'],
                    'odoo_id' => $clientResult['partner_id']
                ]);
                
            } catch (\Exception $e) {
                Log::error('Client creation failed during pending account completion', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                // Set fallback partner_id and odoo_id using user ID
                $fallbackPartnerId = 'PARTNER_' . $user->id;
                $user->update([
                    'partner_id' => $fallbackPartnerId,
                    'odoo_id' => $fallbackPartnerId
                ]);
                
                $clientIntegration = [
                    'success' => false,
                    'partner_id' => $fallbackPartnerId,
                    'message' => 'Client creation failed: ' . $e->getMessage() . '. Using fallback ID.'
                ];
                
                Log::info('User updated with fallback partner_id', [
                    'user_id' => $user->id,
                    'partner_id' => $fallbackPartnerId,
                    'odoo_id' => $fallbackPartnerId
                ]);
            }

            DB::commit();

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

    private function isIraqNumber(?string $phone): bool
    {
        if (!$phone) {
            return false;
        }
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        return str_starts_with($normalized, '964');
    }
}


