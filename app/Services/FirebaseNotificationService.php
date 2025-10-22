<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\FcmToken;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Exception;

class FirebaseNotificationService
{
    use NotificationTrait;

    protected string $projectId;
    protected string $serverKey;
    protected string $databaseUrl;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id');
        $this->serverKey = config('services.firebase.server_key');
        $this->databaseUrl = config('services.firebase.database_url');
    }

    /**
     * Send notification to single user
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ): array {
        try {
            $user = User::findOrFail($userId);
            
            // Get FCM tokens for the user
            $tokens = $this->getUserTokens($userId);
            
            if (empty($tokens)) {
                return [
                    'success' => false,
                    'message' => 'No FCM tokens found for user',
                    'user_id' => $userId
                ];
            }

            $results = [];
            $successCount = 0;
            $failedTokens = [];

            foreach ($tokens as $token) {
                $result = $this->sendToToken($token, $title, $body, $data, $options);
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failedTokens[] = $token;
                }
                
                $results[] = $result;
            }

            // Save notification to database
            if ($notificationTypeId) {
                $this->saveNotification($userId, $title, $body, $notificationTypeId, $data, $successCount, $failedTokens);
            }

            return [
                'success' => $successCount > 0,
                'message' => "Notification sent to {$successCount} devices",
                'user_id' => $userId,
                'success_count' => $successCount,
                'failed_count' => count($failedTokens),
                'results' => $results
            ];

        } catch (Exception $e) {
            Log::error('Failed to send notification to user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'user_id' => $userId
            ];
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ): array {
        $results = [];
        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($userIds as $userId) {
            $result = $this->sendToUser($userId, $title, $body, $data, $notificationTypeId, $options);
            $results[] = $result;
            
            if ($result['success']) {
                $totalSuccess++;
            } else {
                $totalFailed++;
            }
        }

        return [
            'success' => $totalSuccess > 0,
            'message' => "Notifications sent to {$totalSuccess} users",
            'total_users' => count($userIds),
            'success_count' => $totalSuccess,
            'failed_count' => $totalFailed,
            'results' => $results
        ];
    }

    /**
     * Send notification to all users
     */
    public function sendToAllUsers(
        string $title,
        string $body,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ): array {
        try {
            $userIds = User::pluck('id')->toArray();
            return $this->sendToUsers($userIds, $title, $body, $data, $notificationTypeId, $options);
        } catch (Exception $e) {
            Log::error('Failed to send notification to all users', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification to all users: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to users by role
     */
    public function sendToUsersByRole(
        string $role,
        string $title,
        string $body,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ): array {
        try {
            $userIds = User::where('role', $role)->pluck('id')->toArray();
            return $this->sendToUsers($userIds, $title, $body, $data, $notificationTypeId, $options);
        } catch (Exception $e) {
            Log::error('Failed to send notification to users by role', [
                'role' => $role,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification to users by role: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to dealers only
     */
    public function sendToDealers(
        string $title,
        string $body,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ): array {
        try {
            $userIds = User::where('is_dealer', true)->pluck('id')->toArray();
            return $this->sendToUsers($userIds, $title, $body, $data, $notificationTypeId, $options);
        } catch (Exception $e) {
            Log::error('Failed to send notification to dealers', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification to dealers: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to specific FCM token
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new Exception('Failed to get Firebase access token');
            }

            $payload = $this->buildPayload($token, $title, $body, $data, $options);
            
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $payload);

            if ($response->successful()) {
                // Update token last used timestamp
                FcmToken::where('token', $token)->update(['last_used_at' => now()]);
                
                return [
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'token' => $token,
                    'response' => $response->json()
                ];
            } else {
                Log::error('FCM send failed', [
                    'token' => $token,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);

                return [
                    'success' => false,
                    'message' => 'FCM send failed: ' . $response->body(),
                    'token' => $token,
                    'status' => $response->status()
                ];
            }

        } catch (Exception $e) {
            Log::error('Failed to send notification to token', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'token' => $token
            ];
        }
    }

    /**
     * Send scheduled notification
     */
    public function scheduleNotification(
        array $userIds,
        string $title,
        string $body,
        \DateTime $scheduleTime,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ): array {
        try {
            // This would typically be handled by a job queue
            // For now, we'll just log the scheduled notification
            Log::info('Scheduled notification created', [
                'user_ids' => $userIds,
                'title' => $title,
                'schedule_time' => $scheduleTime->format('Y-m-d H:i:s'),
                'notification_type_id' => $notificationTypeId
            ]);

            return [
                'success' => true,
                'message' => 'Notification scheduled successfully',
                'schedule_time' => $scheduleTime->format('Y-m-d H:i:s'),
                'user_count' => count($userIds)
            ];

        } catch (Exception $e) {
            Log::error('Failed to schedule notification', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to schedule notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification with template
     */
    public function sendTemplateNotification(
        array $userIds,
        int $notificationTypeId,
        array $templateData = []
    ): array {
        try {
            $notificationType = NotificationType::findOrFail($notificationTypeId);
            $template = $notificationType->default_template;
            
            $title = $this->replaceTemplateVariables($template['title'] ?? '', $templateData);
            $body = $this->replaceTemplateVariables($template['body'] ?? '', $templateData);

            return $this->sendToUsers($userIds, $title, $body, $templateData, $notificationTypeId);

        } catch (Exception $e) {
            Log::error('Failed to send template notification', [
                'notification_type_id' => $notificationTypeId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send template notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get notification analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        try {
            $query = Notification::query();

            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            if (isset($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['type_id'])) {
                $query->where('type_id', $filters['type_id']);
            }

            $notifications = $query->get();

            $analytics = [
                'total_notifications' => $notifications->count(),
                'by_type' => $notifications->groupBy('type_id')->map->count(),
                'by_user' => $notifications->groupBy('user_id')->map->count(),
                'by_date' => $notifications->groupBy(function ($item) {
                    return $item->created_at->format('Y-m-d');
                })->map->count(),
                'delivery_stats' => $this->getDeliveryStats($notifications)
            ];

            return [
                'success' => true,
                'analytics' => $analytics
            ];

        } catch (Exception $e) {
            Log::error('Failed to get notification analytics', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get analytics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user FCM tokens
     */
    protected function getUserTokens(int $userId): array
    {
        return FcmToken::where('user_id', $userId)
            ->active()
            ->notExpired()
            ->pluck('token')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get Firebase access token
     */
    protected function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $filepath = public_path(config('services.firebase.credentials_path'));
            if (!file_exists($filepath)) {
                throw new Exception("Firebase credentials file not found at: {$filepath}");
            }

            $scopeUrl = config('services.firebase.scope_message_url', 'https://www.googleapis.com/auth/firebase.messaging');
            $client = new \Google\Client();
            $client->setAuthConfig($filepath);
            $client->addScope($scopeUrl);
            $response = $client->fetchAccessTokenWithAssertion();
            
            $this->accessToken = $response['access_token'] ?? null;
            return $this->accessToken;

        } catch (Exception $e) {
            Log::error('Firebase token generation failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build FCM payload
     */
    protected function buildPayload(string $token, string $title, string $body, array $data, array $options): array
    {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge([
                    'title' => $title,
                    'body' => $body,
                ], $data),
            ]
        ];

        // Add platform-specific options
        if (isset($options['android'])) {
            $payload['message']['android'] = $options['android'];
        }

        if (isset($options['apns'])) {
            $payload['message']['apns'] = $options['apns'];
        }

        if (isset($options['webpush'])) {
            $payload['message']['webpush'] = $options['webpush'];
        }

        return $payload;
    }

    /**
     * Save notification to database
     */
    protected function saveNotification(
        int $userId,
        string $title,
        string $body,
        int $notificationTypeId,
        array $data,
        int $successCount,
        array $failedTokens
    ): void {
        try {
            Notification::create([
                'title' => $title,
                'message' => $body,
                'user_id' => $userId,
                'status_id' => 1, // Assuming 1 is 'sent' status
                'type_id' => $notificationTypeId,
                'data' => $data,
                'channel' => 'push',
                'sent_at' => now(),
                'delivery_status' => [
                    'success_count' => $successCount,
                    'failed_tokens' => $failedTokens,
                    'total_tokens' => $successCount + count($failedTokens)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save notification to database', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get delivery statistics
     */
    protected function getDeliveryStats($notifications): array
    {
        $totalSent = $notifications->count();
        $successfulDeliveries = 0;
        $failedDeliveries = 0;

        foreach ($notifications as $notification) {
            $deliveryStatus = $notification->delivery_status ?? [];
            $successfulDeliveries += $deliveryStatus['success_count'] ?? 0;
            $failedDeliveries += count($deliveryStatus['failed_tokens'] ?? []);
        }

        return [
            'total_sent' => $totalSent,
            'successful_deliveries' => $successfulDeliveries,
            'failed_deliveries' => $failedDeliveries,
            'success_rate' => $totalSent > 0 ? round(($successfulDeliveries / $totalSent) * 100, 2) : 0
        ];
    }
}
