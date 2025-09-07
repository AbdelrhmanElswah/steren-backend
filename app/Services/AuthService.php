<?php

namespace App\Services;

use App\Models\User;
use App\Traits\OtpHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    use OtpHelper;

    /**
     * Register a new user and send OTP.
     */
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

    /**
     * Login with email or phone + password.
     */
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

    /**
     * Send OTP to phone.
     */
    public function sendOtp(string $phone): array
    {
        $otp = $this->generateOtp();
        $this->storeOtp($this->phoneKey($phone), $otp['code']);

        return ['sent' => true];
    }

    /**
     * Verify OTP for the given phone.
     */
    public function verifyOtp(string $phone, string $code): array
    {
        if (!$this->validateOtp($this->phoneKey($phone), $code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code'],
            ]);
        }

        $this->clearOtp($this->phoneKey($phone));

        $user = User::query()->firstOrCreate(['phone' => $phone]);
        $user->markAsVerified();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout by deleting current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Start password reset via OTP.
     */
    public function startPasswordReset(string $phone): array
    {
        $user = User::query()->where('phone', $phone)->firstOrFail();
        $otp = $this->generateOtp();
        $this->storeOtp($this->phoneKey($phone), $otp['code']);
        return ['sent' => true, 'user' => $user];
    }

    /**
     * Complete password reset using OTP and new password.
     */
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


