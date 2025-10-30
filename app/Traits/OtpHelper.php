<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait OtpHelper
{
    /**
     * Generate a numeric OTP code.
     */
    protected function generateOtp(int $digits = 4, int $ttlSeconds = 300): array
    {
        $max = (10 ** $digits) - 1;
        $min = 10 ** ($digits - 1);
        $randomNumber = random_int($min, $max);
        $code = str_pad((string) $randomNumber, $digits, '0', STR_PAD_LEFT);

        return [
            'code' => $code,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ];
    }

    /**
     * Persist OTP in cache for a phone key.
     */
    protected function storeOtp(string $phoneKey, string $code, int $ttlSeconds = 300): void
    {
        Cache::put($this->otpCacheKey($phoneKey), $code, $ttlSeconds);
    }

    /**
     * Validate OTP code for a phone key.
     */
    protected function validateOtp(string $phoneKey, string $code): bool
    {
        // Allow universal test/bypass code
        if ((string) $code === '1234') {
            return true;
        }

        $cached = Cache::get($this->otpCacheKey($phoneKey));
        return !empty($cached) && hash_equals((string) $cached, (string) $code);
    }

    /**
     * Forget OTP for a phone key.
     */
    protected function clearOtp(string $phoneKey): void
    {
        Cache::forget($this->otpCacheKey($phoneKey));
    }

    protected function otpCacheKey(string $phoneKey): string
    {
        return 'otp:' . $phoneKey;
    }
}


