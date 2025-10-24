<?php

namespace App\Traits;

use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\FcmToken;
use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;

trait NotificationTrait
{
    /**
     * Send notification to multiple users with FCM and save to database
     */
    private function sendNotification(
        array $userIds, 
        string $title, 
        string $body, 
        int $userId, 
        int $notificationTypeId, 
        array $data = [],
        string $staticKey = null
    ) {
        try {
            // Get active FCM tokens for the users
            $deviceTokens = FcmToken::whereIn('user_id', $userIds)
                ->active()
                ->notExpired()
                ->pluck('token')
                ->unique()
                ->values()
                ->toArray();

            if (empty($deviceTokens)) {
                Log::warning('No valid device tokens found for notification', [
                    'user_ids' => $userIds,
                    'title' => $title
                ]);
                return "No valid device tokens found.";
            }

            // Get Firebase access token
            $accessToken = $this->getFireBaseAccessToken();
            if (!$accessToken) {
                throw new Exception("Failed to get Firebase access token.");
            }

            $projectId = env('FIREBASE_PROJECT_ID', 'steren-3d035');
            $successCount = 0;
            $failedTokens = [];

            // Send to each device token
            foreach ($deviceTokens as $token) {
                try {
                    $payload = [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'android' => [
                                'notification' => [
                                    'sound' => 'default',
                                    'channel_id' => 'default',
                                ],
                            ],
                            'apns' => [
                                'payload' => [
                                    'aps' => [
                                        'sound' => 'default',
                                        'content-available' => 1,
                                    ],
                                ],
                            ],
                            'webpush' => [
                                'notification' => [
                                    'sound' => 'default',
                                ],
                            ],
                            'data' => array_merge([
                                'silent' => 'true',
                                'title' => $title,
                                'body' => $body,
                                'notification_type_id' => $notificationTypeId,
                            ], $data),
                        ]
                    ];

                    $response = Http::withToken($accessToken)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(30)
                        ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

                    if ($response->successful()) {
                        $successCount++;
                        // Update last used timestamp for the token
                        FcmToken::where('token', $token)->update(['last_used_at' => now()]);
                    } else {
                        $failedTokens[] = $token;
                        Log::error('FCM send failed', [
                            'token' => $token,
                            'response' => $response->body(),
                            'status' => $response->status()
                        ]);
                    }
                } catch (Exception $e) {
                    $failedTokens[] = $token;
                    Log::error('FCM send exception', [
                        'token' => $token,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Save notification in database for each user
            $notificationType = NotificationType::find($notificationTypeId);
            if (!$notificationType) {
                throw new Exception("Invalid Notification Type ID: {$notificationTypeId}");
            }

            foreach ($userIds as $targetUserId) {
                Notification::create([
                    'title' => $title,
                    'message' => $body,
                    'user_id' => $targetUserId,
                    'type' => $notificationType->name ?? 'general',
                    'data' => $data,
                ]);
            }

            Log::info('Notification sent successfully', [
                'title' => $title,
                'user_count' => count($userIds),
                'success_count' => $successCount,
                'failed_count' => count($failedTokens)
            ]);

            return "Notification sent to {$successCount} devices and saved in the database.";

        } catch (Exception $e) {
            Log::error('Notification send failed', [
                'error' => $e->getMessage(),
                'user_ids' => $userIds,
                'title' => $title
            ]);
            throw new Exception("Failed to send notification: " . $e->getMessage());
        }
    }

    /**
     * Send push notification to a single FCM token
     */
    public function sendPushNotification($fcm_token, $title, $body, $data = [])
    {
        try {
            $token = $this->getFireBaseAccessToken();
            if (!$token) {
                throw new Exception("Failed to get Firebase access token.");
            }

            $URL = env('FIREBASE_URL');
            if (!$URL) {
                throw new Exception("FIREBASE_URL not configured.");
            }

            $post_data = [
                "message" => [
                    "token" => $fcm_token,
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                    ],
                    "data" => $data,
                ]
            ];

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($URL, $post_data);

            if ($response->successful()) {
                // Update token last used timestamp
                FcmToken::where('token', $fcm_token)->update(['last_used_at' => now()]);
                return "Firebase notification sent successfully!";
            } else {
                throw new Exception("FCM API error: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error('Push notification failed', [
                'error' => $e->getMessage(),
                'token' => $fcm_token,
                'title' => $title
            ]);
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Get Firebase access token using service account
     */
    private function getFireBaseAccessToken()
    {
        try {
            $filepath = storage_path('app/private/google-services.json');
            if (!file_exists($filepath)) {
                throw new Exception("Google credentials file not found at: {$filepath}");
            }

            $scopeUrl = env('FIREBASE_SCOPE_MESSAGE_URL', 'https://www.googleapis.com/auth/firebase.messaging');
            $client = new GoogleClient();
            $client->setAuthConfig($filepath);
            $client->addScope($scopeUrl);
            $response = $client->fetchAccessTokenWithAssertion();
            
            return $response['access_token'] ?? false;
        } catch (Exception $e) {
            Log::error('Firebase token generation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification to a single user
     */
    public function sendNotificationToUser(
        int $userId, 
        string $title, 
        string $body, 
        int $notificationTypeId, 
        array $data = []
    ) {
        return $this->sendNotification([$userId], $title, $body, $userId, $notificationTypeId, $data);
    }

    /**
     * Send notification to multiple users
     */
    public function sendNotificationToUsers(
        array $userIds, 
        string $title, 
        string $body, 
        int $notificationTypeId, 
        array $data = []
    ) {
        return $this->sendNotification($userIds, $title, $body, $userIds[0] ?? 1, $notificationTypeId, $data);
    }

    /**
     * Send notification using notification type template
     */
    public function sendTemplateNotification(
        array $userIds, 
        int $notificationTypeId, 
        array $templateData = []
    ) {
        $notificationType = NotificationType::find($notificationTypeId);
        if (!$notificationType) {
            throw new Exception("Notification type not found: {$notificationTypeId}");
        }

        $template = $notificationType->default_template;
        $title = $this->replaceTemplateVariables($template['title'] ?? '', $templateData);
        $body = $this->replaceTemplateVariables($template['body'] ?? '', $templateData);

        return $this->sendNotification($userIds, $title, $body, $userIds[0] ?? 1, $notificationTypeId, $templateData);
    }

    /**
     * Replace template variables with actual data
     */
    private function replaceTemplateVariables(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    /**
     * Register FCM token for a user
     */
    public function registerFcmToken(int $userId, string $token, array $deviceInfo = [])
    {
        try {
            return FcmToken::updateOrCreate(
                ['token' => $token],
                [
                    'user_id' => $userId,
                    'device_id' => $deviceInfo['device_id'] ?? null,
                    'device_type' => $deviceInfo['device_type'] ?? 'mobile',
                    'platform' => $deviceInfo['platform'] ?? null,
                    'app_version' => $deviceInfo['app_version'] ?? null,
                    'os_version' => $deviceInfo['os_version'] ?? null,
                    'device_info' => $deviceInfo,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );
        } catch (Exception $e) {
            Log::error('FCM token registration failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Deactivate FCM token
     */
    public function deactivateFcmToken(string $token)
    {
        return FcmToken::where('token', $token)->update(['is_active' => false]);
    }
}
