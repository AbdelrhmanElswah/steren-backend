<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\NotificationTrait;
use App\Models\User;
use App\Models\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestNotificationController extends Controller
{
    use ApiResponse, NotificationTrait;

    /**
     * Test notification system by sending a test notification
     */
    public function testNotification(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $title = $request->input('title', 'Test Notification');
            $body = $request->input('body', 'This is a test notification to verify the system is working.');
            
            // Get or create a test notification type
            $notificationType = NotificationType::firstOrCreate(
                ['name' => 'test_notification'],
                [
                    'name' => 'test_notification',
                    'display_name' => 'Test Notification',
                    'description' => 'Test notification for system verification',
                    'icon' => 'test_tube',
                    'color' => '#3B82F6',
                    'is_active' => true,
                    'default_template' => [
                        'title' => 'Test Notification',
                        'body' => 'This is a test notification.'
                    ],
                    'channels' => ['push'],
                    'sort_order' => 999
                ]
            );

            // Send test notification
            $result = $this->sendNotificationToUser(
                $userId,
                $title,
                $body,
                $notificationType->id,
                [
                    'test_data' => 'This is test data',
                    'timestamp' => now()->toISOString()
                ]
            );

            Log::info('Test notification sent', [
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'result' => $result
            ]);

            return $this->successResponse([
                'message' => 'Test notification sent successfully',
                'result' => $result,
                'notification_type_id' => $notificationType->id
            ], 'Test notification sent successfully');

        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return $this->errorResponse(
                'Failed to send test notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Test FCM token registration
     */
    public function testFcmTokenRegistration(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $token = $request->input('fcm_token');
            $deviceInfo = $request->input('device_info', []);

            if (!$token) {
                return $this->errorResponse('FCM token is required', 400);
            }

            // Register FCM token
            $fcmToken = $this->registerFcmToken($userId, $token, $deviceInfo);
            dd($fcmToken);

            Log::info('FCM token registered for testing', [
                'user_id' => $userId,
                'token' => $token,
                'fcm_token_id' => $fcmToken->id
            ]);

            return $this->successResponse([
                'message' => 'FCM token registered successfully',
                'fcm_token_id' => $fcmToken->id,
                'token' => $fcmToken->token,
                'is_active' => $fcmToken->is_active
            ], 'FCM token registered successfully');

        } catch (\Exception $e) {
            Log::error('FCM token registration failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return $this->errorResponse(
                'Failed to register FCM token: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Test push notification to specific FCM token
     */
    public function testPushNotification(Request $request)
    {
        try {
            $fcmToken = $request->input('fcm_token');
            $title = $request->input('title', 'Test Push Notification');
            $body = $request->input('body', 'This is a test push notification.');
            $data = $request->input('data', []);

            if (!$fcmToken) {
                return $this->errorResponse('FCM token is required', 400);
            }

            // Send push notification
            $result = $this->sendPushNotification($fcmToken, $title, $body, $data);

            Log::info('Test push notification sent', [
                'fcm_token' => $fcmToken,
                'title' => $title,
                'body' => $body,
                'result' => $result
            ]);

            return $this->successResponse([
                'message' => 'Test push notification sent successfully',
                'result' => $result
            ], 'Test push notification sent successfully');

        } catch (\Exception $e) {
            Log::error('Test push notification failed', [
                'error' => $e->getMessage(),
                'fcm_token' => $request->input('fcm_token')
            ]);

            return $this->errorResponse(
                'Failed to send test push notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get notification types for testing
     */
    public function getNotificationTypes()
    {
        try {
            $notificationTypes = NotificationType::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return $this->successResponse([
                'notification_types' => $notificationTypes
            ], 'Notification types retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to get notification types', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to get notification types: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Test notification with template
     */
    public function testTemplateNotification(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $notificationTypeId = $request->input('notification_type_id');
            $templateData = $request->input('template_data', []);

            if (!$notificationTypeId) {
                return $this->errorResponse('Notification type ID is required', 400);
            }

            // Send template notification
            $result = $this->sendTemplateNotification(
                [$userId],
                $notificationTypeId,
                $templateData
            );

            Log::info('Test template notification sent', [
                'user_id' => $userId,
                'notification_type_id' => $notificationTypeId,
                'template_data' => $templateData,
                'result' => $result
            ]);

            return $this->successResponse([
                'message' => 'Test template notification sent successfully',
                'result' => $result
            ], 'Test template notification sent successfully');

        } catch (\Exception $e) {
            Log::error('Test template notification failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return $this->errorResponse(
                'Failed to send test template notification: ' . $e->getMessage(),
                500
            );
        }
    }
}
