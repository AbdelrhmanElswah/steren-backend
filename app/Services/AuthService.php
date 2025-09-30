<?php

namespace App\Services;

use App\Models\User;
use App\Services\OdooService;
use App\Services\ClientService;
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
        private ClientService $clientService
    ) {
    }

    public function register(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $user = User::query()->create([
                'full_name' => $payload['full_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'password' => isset($payload['password']) ? Hash::make($payload['password']) : null,
            ]);

            $otp = $this->generateOtp();
            $this->storeOtp($this->phoneKey($user->phone), $otp['code']);

            return [
                'user' => $user,
                'otp_sent' => true,
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

        return ['sent' => true];
    }

    public function verifyOtp(string $phone, string $code, ?array $clientData = null): array
    {
        if (!$this->validateOtp($this->phoneKey($phone), $code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code'],
            ]);
        }

        $this->clearOtp($this->phoneKey($phone));

        $user = User::query()->firstOrCreate(['phone' => $phone]);
        $user->markAsVerified();

        $clientIntegration = [
            'success' => false,
            'partner_id' => null,
            'message' => 'Client creation not attempted'
        ];

        if ($clientData && isset($clientData['full_name'], $clientData['email'])) {
            try {
                $clientResult = $this->clientService->createClient([
                    'full_name' => $clientData['full_name'],
                    'phone' => $phone,
                    'email' => $clientData['email']
                ]);

                $clientIntegration = [
                    'success' => $clientResult['success'],
                    'partner_id' => $clientResult['partner_id'],
                    'message' => $clientResult['message']
                ];

                // Update user with partner_id
                $user->update(['partner_id' => $clientResult['partner_id']]);

            } catch (\Exception $e) {
                Log::error('Client creation failed during OTP verification', [
                    'user_id' => $user->id,
                    'phone' => $phone,
                    'client_data' => $clientData,
                    'error' => $e->getMessage()
                ]);

                $clientIntegration = [
                    'success' => false,
                    'partner_id' => null,
                    'message' => 'Client creation failed: ' . $e->getMessage()
                ];
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

    public function startPasswordReset(string $phone): array
    {
        $user = User::query()->where('phone', $phone)->firstOrFail();
        $otp = $this->generateOtp();
        $this->storeOtp($this->phoneKey($phone), $otp['code']);
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

    protected function phoneKey(?string $phone): string
    {
        return (string) $phone;
    }
}


