<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\FcmToken;
use App\Traits\NotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    use NotificationTrait;

    /**
     * Get user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 15);
            $type = $request->get('type'); // all, unread, read
            $notificationType = $request->get('notification_type_id');

            $query = Notification::with(['type', 'status'])
                ->where('user_id', $user->id);

            // Filter by read status
            switch ($type) {
                case 'unread':
                    $query->unread();
                    break;
                case 'read':
                    $query->read();
                    break;
                // 'all' or no filter - show all
            }

            // Filter by notification type
            if ($notificationType) {
                $query->byType($notificationType);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification->fresh(['type', 'status'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as clicked
     */
    public function markAsClicked(Request $request, int $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsClicked();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as clicked',
                'data' => $notification->fresh(['type', 'status'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as clicked',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $updated = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} notifications as read"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(int $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification types
     */
    public function getNotificationTypes(): JsonResponse
    {
        try {
            $types = NotificationType::active()
                ->ordered()
                ->get(['id', 'name', 'display_name', 'description', 'icon', 'color']);

            return response()->json([
                'success' => true,
                'data' => $types,
                'message' => 'Notification types retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register FCM token
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'device_id' => 'nullable|string',
                'device_type' => 'nullable|string|in:mobile,tablet,web',
                'platform' => 'nullable|string|in:ios,android,web',
                'app_version' => 'nullable|string',
                'os_version' => 'nullable|string',
                'device_info' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $deviceInfo = $request->only([
                'device_id', 'device_type', 'platform', 
                'app_version', 'os_version', 'device_info'
            ]);

            $fcmToken = $this->registerFcmToken($user->id, $request->token, $deviceInfo);

            return response()->json([
                'success' => true,
                'data' => $fcmToken,
                'message' => 'FCM token registered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate FCM token
     */
    public function deactivateFcmToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $token = FcmToken::where('token', $request->token)
                ->where('user_id', $user->id)
                ->first();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'FCM token not found'
                ], 404);
            }

            $this->deactivateFcmToken($request->token);

            return response()->json([
                'success' => true,
                'message' => 'FCM token deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate FCM token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $stats = [
                'total' => Notification::where('user_id', $user->id)->count(),
                'unread' => Notification::where('user_id', $user->id)->unread()->count(),
                'read' => Notification::where('user_id', $user->id)->read()->count(),
                'clicked' => Notification::where('user_id', $user->id)->whereNotNull('clicked_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Notification statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}