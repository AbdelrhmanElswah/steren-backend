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
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\AuthenticationException;
use Exception;

class NotificationController extends Controller
{
    use ApiResponse;

    protected ?FirebaseNotificationService $firebaseService = null;

    public function __construct()
    {
        // Firebase service is optional - only inject if Firebase is configured
        try {
            if (env('FIREBASE_SERVER_KEY') || config('services.firebase.server_key')) {
                $this->firebaseService = app(FirebaseNotificationService::class);
            }
        } catch (\Exception $e) {
            // Firebase not configured - continue without it
            Log::warning('Firebase service not available', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Ensure user is authenticated
     */
    private function ensureAuthenticated(Request $request): ?JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            return null;
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (\Exception $e) {
            Log::warning('Authentication check failed in NotificationController', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('User not authenticated', 401);
        }
    }

    /**
     * Check if Firebase service is available
     */
    private function checkFirebaseService()
    {
        if (!$this->firebaseService) {
            return $this->errorResponse('Firebase service not configured', 503);
        }
        return null;
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

            if ($error = $this->checkFirebaseService()) {
                return $error;
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

            if ($error = $this->checkFirebaseService()) {
                return $error;
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

            if ($error = $this->checkFirebaseService()) {
                return $error;
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

            if ($error = $this->checkFirebaseService()) {
                return $error;
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

            if ($error = $this->checkFirebaseService()) {
                return $error;
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

            if ($error = $this->checkFirebaseService()) {
                return $error;
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
     * Get unread notification count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $unreadCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse([
                'unread_count' => $unreadCount
            ], 'Unread count retrieved successfully');

        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to get unread count', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse(
                'Failed to get unread count: ' . $e->getMessage(),
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $notifications = Notification::where('user_id', $user->id)
                ->with('notificationType:id,name,display_name,description,icon,color,is_active,sort_order')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Format notifications to include type details
            $formattedNotifications = $notifications->getCollection()->map(function ($notification) {
                $notificationData = [
                    'id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'is_read' => $notification->is_read,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->updated_at,
                ];

                // Include notification type details if available
                if ($notification->notificationType) {
                    $notificationData['notification_type'] = [
                        'id' => $notification->notificationType->id,
                        'name' => $notification->notificationType->name,
                        'display_name' => $notification->notificationType->display_name,
                        'description' => $notification->notificationType->description,
                        'icon' => $notification->notificationType->icon,
                        'color' => $notification->notificationType->color,
                        'is_active' => $notification->notificationType->is_active,
                        'sort_order' => $notification->notificationType->sort_order,
                    ];
                } else {
                    $notificationData['notification_type'] = null;
                }

                return $notificationData;
            });

            // Get unread count
            $unreadCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse([
                'notifications' => $formattedNotifications,
                'unread_count' => $unreadCount,
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'has_more' => $notifications->hasMorePages()
                ]
            ], 'User notifications retrieved successfully');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to get user notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->with('notificationType:id,name,display_name,description,icon,color,is_active,sort_order')
                ->firstOrFail();

            $notification->update([
                'read_at' => now(),
                'is_read' => true
            ]);

            // Refresh to get updated read_at
            $notification->refresh();

            // Format notification with type details
            $notificationData = [
                'id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
            ];

            // Include notification type details if available
            if ($notification->notificationType) {
                $notificationData['notification_type'] = [
                    'id' => $notification->notificationType->id,
                    'name' => $notification->notificationType->name,
                    'display_name' => $notification->notificationType->display_name,
                    'description' => $notification->notificationType->description,
                    'icon' => $notification->notificationType->icon,
                    'color' => $notification->notificationType->color,
                    'is_active' => $notification->notificationType->is_active,
                    'sort_order' => $notification->notificationType->sort_order,
                ];
            } else {
                $notificationData['notification_type'] = null;
            }

            // Get updated unread count
            $unreadCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse([
                'notification' => $notificationData,
                'unread_count' => $unreadCount
            ], 'Notification marked as read');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to mark notification as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $updated = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'read_at' => now(),
                    'is_read' => true
                ]);

            // Get updated unread count (should be 0)
            $unreadCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse([
                'updated_count' => $updated,
                'unread_count' => $unreadCount
            ], 'All notifications marked as read');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $notification->delete();

            return $this->successResponse([
                'notification_id' => $notificationId
            ], 'Notification deleted successfully');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to delete notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            if ($error = $this->checkFirebaseService()) {
                return $error;
            }

            $filters = $request->only(['date_from', 'date_to', 'user_id', 'type_id']);
            
            $result = $this->firebaseService->getAnalytics($filters);

            return $this->successResponse($result, 'Analytics retrieved successfully');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to get analytics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse(
                'Failed to get analytics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get notification types
     */
    public function getNotificationTypes(Request $request): JsonResponse
    {
        try {
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $types = NotificationType::select('id', 'name', 'display_name', 'description', 'icon', 'color', 'is_active', 'sort_order')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return $this->successResponse($types, 'Notification types retrieved successfully');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to get notification types', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

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

            if ($error = $this->checkFirebaseService()) {
                return $error;
            }

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

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to register FCM token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // Check authentication first - return 401 if not authenticated
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $validator = Validator::make($request->all(), [
                'token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            if ($error = $this->checkFirebaseService()) {
                return $error;
            }

            $result = $this->firebaseService->deactivateFcmToken($request->token);

            return $this->successResponse([
                'token' => $request->token,
                'deactivated' => $result
            ], 'FCM token deactivated successfully');

        } catch (AuthenticationException $e) {
            return $this->errorResponse('User not authenticated', 401);
        } catch (Exception $e) {
            Log::error('Failed to deactivate FCM token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse(
                'Failed to deactivate FCM token: ' . $e->getMessage(),
                500
            );
        }
    }
}