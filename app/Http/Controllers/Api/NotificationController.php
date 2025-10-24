<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseNotificationService;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class NotificationController extends Controller
{
    use ApiResponse;

    protected FirebaseNotificationService $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification to single user
     */
    public function sendToUser(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array',
                'notification_type_id' => 'nullable|integer|exists:notification_types,id',
                'options' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $result = $this->firebaseService->sendToUser(
                $request->user_id,
                $request->title,
                $request->body,
                $request->data ?? [],
                $request->notification_type_id,
                $request->options ?? []
            );

            return $this->successResponse($result, 'Notification sent successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to send notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array',
                'notification_type_id' => 'nullable|integer|exists:notification_types,id',
                'options' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $result = $this->firebaseService->sendToUsers(
                $request->user_ids,
                $request->title,
                $request->body,
                $request->data ?? [],
                $request->notification_type_id,
                $request->options ?? []
            );

            return $this->successResponse($result, 'Notifications sent successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to send notifications: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send notification to all users
     */
    public function sendToAll(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array',
                'notification_type_id' => 'nullable|integer|exists:notification_types,id',
                'options' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $result = $this->firebaseService->sendToAllUsers(
                $request->title,
                $request->body,
                $request->data ?? [],
                $request->notification_type_id,
                $request->options ?? []
            );

            return $this->successResponse($result, 'Notifications sent to all users successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to send notifications to all users: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send notification to dealers
     */
    public function sendToDealers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array',
                'notification_type_id' => 'nullable|integer|exists:notification_types,id',
                'options' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $result = $this->firebaseService->sendToDealers(
                $request->title,
                $request->body,
                $request->data ?? [],
                $request->notification_type_id,
                $request->options ?? []
            );

            return $this->successResponse($result, 'Notifications sent to dealers successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to send notifications to dealers: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send template notification
     */
    public function sendTemplate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'notification_type_id' => 'required|integer|exists:notification_types,id',
                'template_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $result = $this->firebaseService->sendTemplateNotification(
                $request->user_ids,
                $request->notification_type_id,
                $request->template_data ?? []
            );

            return $this->successResponse($result, 'Template notification sent successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to send template notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Schedule notification
     */
    public function schedule(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'schedule_time' => 'required|date|after:now',
                'data' => 'nullable|array',
                'notification_type_id' => 'nullable|integer|exists:notification_types,id',
                'options' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $scheduleTime = new \DateTime($request->schedule_time);
            
            $result = $this->firebaseService->scheduleNotification(
                $request->user_ids,
                $request->title,
                $request->body,
                $scheduleTime,
                $request->data ?? [],
                $request->notification_type_id,
                $request->options ?? []
            );

            return $this->successResponse($result, 'Notification scheduled successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to schedule notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->successResponse([
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'has_more' => $notifications->hasMorePages()
                ]
            ], 'User notifications retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to get user notifications: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        try {
            $user = $request->user();
            
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $notification->update([
                'read_at' => now(),
                'is_read' => true
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'read_at' => $notification->read_at
            ], 'Notification marked as read');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to mark notification as read: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $updated = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update([
                    'read_at' => now(),
                    'is_read' => true
                ]);

            return $this->successResponse([
                'updated_count' => $updated
            ], 'All notifications marked as read');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to mark all notifications as read: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete notification
     */
    public function delete(Request $request, int $notificationId): JsonResponse
    {
        try {
            $user = $request->user();
            
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $notification->delete();

            return $this->successResponse([
                'notification_id' => $notificationId
            ], 'Notification deleted successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to delete notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get notification analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'user_id', 'type_id']);
            
            $result = $this->firebaseService->getAnalytics($filters);

            return $this->successResponse($result, 'Analytics retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to get analytics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get notification types
     */
    public function getNotificationTypes(): JsonResponse
    {
        try {
            $types = NotificationType::select('id', 'name', 'display_name', 'description')
                ->get();

            return $this->successResponse($types, 'Notification types retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to get notification types: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Register FCM token
     */
    public function registerToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'device_id' => 'nullable|string',
                'device_type' => 'nullable|string|in:mobile,tablet,desktop',
                'platform' => 'nullable|string|in:ios,android,web',
                'app_version' => 'nullable|string',
                'os_version' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = $request->user();
            $deviceInfo = $request->only(['device_id', 'device_type', 'platform', 'app_version', 'os_version']);

            $fcmToken = $this->firebaseService->registerFcmToken(
                $user->id,
                $request->token,
                $deviceInfo
            );

            return $this->successResponse([
                'token_id' => $fcmToken->id,
                'token' => $fcmToken->token,
                'is_active' => $fcmToken->is_active
            ], 'FCM token registered successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to register FCM token: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Deactivate FCM token
     */
    public function deactivateToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $result = $this->firebaseService->deactivateFcmToken($request->token);

            return $this->successResponse([
                'token' => $request->token,
                'deactivated' => $result
            ], 'FCM token deactivated successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to deactivate FCM token: ' . $e->getMessage(),
                500
            );
        }
    }
}