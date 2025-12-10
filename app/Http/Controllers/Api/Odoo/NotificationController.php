<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\CreateNotificationRequest;
use App\Models\User;
use App\Models\NotificationType;
use App\Services\DatabaseNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DatabaseNotificationService $notificationService
    ) {}

    /**
     * Create a rejection notification for a user by partner_id
     * 
     * This endpoint is used by Odoo admin to create rejection notifications
     * after rejecting a listing or listing edit.
     * 
     * @param CreateNotificationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createNotification(CreateNotificationRequest $request)
    {
        try {
            $partnerId = $request->input('partner_id');
            $type = $request->input('type');
            $title = $request->input('title'); // Now an array with en/ar/kr keys
            $message = $request->input('message'); // Now an array with en/ar/kr keys

            // Validate notification type first
            $notificationType = NotificationType::findByName($type);
            
            if (!$notificationType) {
                // Get all available notification types
                $availableTypes = NotificationType::where('is_active', true)
                    ->orderBy('sort_order')
                    ->pluck('name')
                    ->toArray();
                
                return $this->errorResponse(
                    "Notification type '{$type}' not found. Available types: " . implode(', ', $availableTypes),
                    422,
                    [
                        'invalid_type' => $type,
                        'available_types' => $availableTypes
                    ]
                );
            }

            // Find user by partner_id
            $user = User::where('partner_id', $partnerId)->first();
            
            if (!$user) {
                return $this->errorResponse(
                    "User with partner_id '{$partnerId}' not found",
                    404
                );
            }

            // Create the notification with translations
            $notification = $this->notificationService->createNotification(
                $user->id,
                $type,
                $title, // Pass as array
                $message, // Pass as array
                [
                    'partner_id' => $partnerId,
                    'notification_type' => $type,
                ],
                $notificationType->id
            );

            Log::info('Notification created by Odoo', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'partner_id' => $partnerId,
                'type' => $type,
                'title' => $title['en'] ?? null,
            ]);

            return $this->successResponse([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'partner_id' => $partnerId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ], 'Notification created successfully');

        } catch (Exception $e) {
            Log::error('Failed to create notification via Odoo API: ' . $e->getMessage(), [
                'partner_id' => $request->input('partner_id'),
                'type' => $request->input('type'),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to create notification: ' . $e->getMessage(),
                500
            );
        }
    }
}

