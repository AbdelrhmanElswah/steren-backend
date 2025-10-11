<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FirebaseService
{
    private ?string $projectId;
    private array $publicKeys;
    private string $publicKeysUrl;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id', '');
        $this->publicKeysUrl = "https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com";
    }

    public function verifyIdToken(string $idToken): array
    {
        try {
            if (empty($this->projectId)) {
                throw new \Exception('Firebase is not configured. Please set FIREBASE_PROJECT_ID in your .env file');
            }
            
            $publicKeys = $this->getPublicKeys();
            
            $header = $this->decodeTokenPart($idToken, 0);
            $kid = $header['kid'] ?? null;
            
            if (!$kid || !isset($publicKeys[$kid])) {
                throw new \Exception('Invalid token: Key ID not found');
            }
            
            $payload = $this->verifyTokenSignature($idToken, $publicKeys[$kid]);
            
            $this->validateTokenClaims($payload);
            
            return $payload;
            
        } catch (\Exception $e) {
            Log::error('Firebase token verification failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($idToken, 0, 20) . '...'
            ]);
            
            throw new \Exception('Invalid Firebase token: ' . $e->getMessage());
        }
    }

    private function getPublicKeys(): array
    {
        return Cache::remember('firebase_public_keys', 3600, function () {
            $response = Http::timeout(10)->get($this->publicKeysUrl);
            
            if (!$response->successful()) {
                throw new \Exception('Failed to fetch Firebase public keys');
            }
            
            return $response->json();
        });
    }

    private function decodeTokenPart(string $token, int $part): array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }
        
        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[$part]));
        
        if (!$decoded) {
            throw new \Exception('Failed to decode token part');
        }
        
        return json_decode($decoded, true);
    }

    private function verifyTokenSignature(string $token, string $publicKey): array
    {
        $parts = explode('.', $token);
        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];
        
        $data = $header . '.' . $payload;
        
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $signature));
        
        $verified = openssl_verify(
            $data,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );
        
        if ($verified !== 1) {
            throw new \Exception('Invalid token signature');
        }
        
        return $this->decodeTokenPart($token, 1);
    }

    private function validateTokenClaims(array $payload): void
    {
        $now = time();
        
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw new \Exception('Token has expired');
        }
        
        if (isset($payload['iat']) && $payload['iat'] > $now) {
            throw new \Exception('Token is not yet valid');
        }
        
        if (isset($payload['aud']) && $payload['aud'] !== $this->projectId) {
            throw new \Exception('Invalid audience');
        }
        
        $expectedIssuer = "https://securetoken.google.com/{$this->projectId}";
        if (isset($payload['iss']) && $payload['iss'] !== $expectedIssuer) {
            throw new \Exception('Invalid issuer');
        }
        
        if (!isset($payload['user_id']) && !isset($payload['sub'])) {
            throw new \Exception('User ID not found in token');
        }
    }


    public function extractUserInfo(array $payload): array
    {
        return [
            'firebase_uid' => $payload['user_id'] ?? $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null,
            'email_verified' => $payload['email_verified'] ?? false,
            'phone_number' => $payload['phone_number'] ?? null,
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
            'firebase_claims' => $payload,
        ];
    }

    public function syncDealerApplicationToFirebase(int $applicationId, string $partnerId, array $data): void
    {
        try {
            $firebaseUrl = config('services.firebase.database_url');
            $webApiKey = config('services.firebase.web_api_key');
            
            if (empty($firebaseUrl)) {
                Log::warning('Firebase database URL not configured, skipping sync');
                return;
            }

            $path = "/dealer_applications/{$partnerId}/{$applicationId}.json";
            
            if ($webApiKey) {
                $url = rtrim($firebaseUrl, '/') . $path . '?auth=' . $webApiKey;
            } else {
                $url = rtrim($firebaseUrl, '/') . $path;
            }

            $response = Http::timeout(10)->put($url, $data);

            if ($response->successful()) {
                Log::info('Dealer application synced to Firebase', [
                    'application_id' => $applicationId,
                    'partner_id' => $partnerId,
                    'firebase_path' => $path,
                ]);
            } else {
                Log::error('Failed to sync dealer application to Firebase', [
                    'application_id' => $applicationId,
                    'partner_id' => $partnerId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Firebase sync error', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateDealerApplicationStatus(string $partnerId, int $applicationId, string $status, ?string $reason = null): void
    {
        try {
            $firebaseUrl = config('services.firebase.database_url');
            $webApiKey = config('services.firebase.web_api_key');
            
            if (empty($firebaseUrl)) {
                Log::warning('Firebase database URL not configured, skipping status update');
                return;
            }

            $path = "/dealer_applications/{$partnerId}/{$applicationId}/status.json";
            
            if ($webApiKey) {
                $url = rtrim($firebaseUrl, '/') . $path . '?auth=' . $webApiKey;
            } else {
                $url = rtrim($firebaseUrl, '/') . $path;
            }

            $data = [
                'status' => $status,
                'updated_at' => now()->toISOString(),
            ];

            if ($reason) {
                $data['rejection_reason'] = $reason;
            }

            $response = Http::timeout(10)->put($url, $data);

            if ($response->successful()) {
                Log::info('Dealer application status updated in Firebase', [
                    'application_id' => $applicationId,
                    'partner_id' => $partnerId,
                    'status' => $status,
                    'firebase_path' => $path,
                ]);
            } else {
                Log::error('Failed to update dealer application status in Firebase', [
                    'application_id' => $applicationId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Firebase status update error', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
