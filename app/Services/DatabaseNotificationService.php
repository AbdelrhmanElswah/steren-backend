<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationType;
use Illuminate\Support\Facades\Log;
use Exception;

class DatabaseNotificationService
{
    /**
     * Create a notification record in the database without sending push notification
     */
    public function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?int $notificationTypeId = null
    ): Notification {
        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'notification_type_id' => $notificationTypeId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'is_read' => false,
                'read_at' => null,
            ]);

            Log::info('Notification created in database', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title
            ]);

            return $notification;

        } catch (Exception $e) {
            Log::error('Failed to create notification in database', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create notification using notification type
     */
    public function createNotificationByType(
        int $userId,
        int $notificationTypeId,
        array $templateData = []
    ): Notification {
        try {
            $notificationType = NotificationType::findOrFail($notificationTypeId);
            
            // Replace template variables if template exists
            $title = $notificationType->default_template['title'] ?? 'Notification';
            $message = $notificationType->default_template['body'] ?? '';
            
            if (!empty($templateData)) {
                $title = $this->replaceTemplateVariables($title, $templateData);
                $message = $this->replaceTemplateVariables($message, $templateData);
            }

            return $this->createNotification(
                $userId,
                $notificationType->name,
                $title,
                $message,
                $templateData
            );

        } catch (Exception $e) {
            Log::error('Failed to create notification by type', [
                'user_id' => $userId,
                'notification_type_id' => $notificationTypeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create listing created notification
     */
    public function createListingCreatedNotification($listing): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('listing_created');
            if (!$notificationType) {
                throw new Exception('Notification type "listing_created" not found. Please run the seeder.');
            }

            $carName = $listing->title ?? 'Your listing';
            if ($listing->brand && $listing->carModel) {
                $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                if ($listing->year) {
                    $carName .= ' ' . $listing->year;
                }
            }

            $title = 'Listing Created Successfully!';
            $message = "Your {$carName} listing has been created and is pending approval.";

            return $this->createNotification(
                $listing->user_id,
                'listing_created',
                $title,
                $message,
                [
                    'car_listing_id' => $listing->id,
                    'car_name' => $carName,
                    'price' => $listing->price,
                    'currency' => $listing->price_currency ?? 'USD',
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create listing created notification', [
                'listing_id' => $listing->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create listing approved notification
     */
    public function createListingApprovedNotification($listing): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('listing_approved');
            if (!$notificationType) {
                throw new Exception('Notification type "listing_approved" not found. Please run the seeder.');
            }

            $carName = $listing->title ?? 'Your listing';
            if ($listing->brand && $listing->carModel) {
                $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                if ($listing->year) {
                    $carName .= ' ' . $listing->year;
                }
            }

            $title = 'Listing Approved!';
            $message = "Great news! Your {$carName} listing has been approved and is now live. Buyers can now see and contact you about this vehicle.";

            return $this->createNotification(
                $listing->user_id,
                'listing_approved',
                $title,
                $message,
                [
                    'car_listing_id' => $listing->id,
                    'car_name' => $carName,
                    'price' => $listing->price,
                    'currency' => $listing->price_currency ?? 'USD',
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create listing approved notification', [
                'listing_id' => $listing->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create listing rejected notification
     */
    public function createListingRejectedNotification($listing, string $reason = null): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('listing_rejected');
            if (!$notificationType) {
                throw new Exception('Notification type "listing_rejected" not found. Please run the seeder.');
            }

            $carName = $listing->title ?? 'Your listing';
            if ($listing->brand && $listing->carModel) {
                $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                if ($listing->year) {
                    $carName .= ' ' . $listing->year;
                }
            }

            $title = 'Listing Rejected';
            $reasonText = $reason ?? 'No reason provided';
            $message = "Your {$carName} listing was not approved. Reason: {$reasonText}. Please review the requirements and submit a new listing.";

            return $this->createNotification(
                $listing->user_id,
                'listing_rejected',
                $title,
                $message,
                [
                    'car_listing_id' => $listing->id,
                    'car_name' => $carName,
                    'reason' => $reasonText,
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create listing rejected notification', [
                'listing_id' => $listing->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
     * Create dealer application submitted notification
     */
    public function createDealerApplicationSubmittedNotification($application): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('dealer_application_submitted');
            if (!$notificationType) {
                throw new Exception('Notification type "dealer_application_submitted" not found. Please run the seeder.');
            }

            $companyName = $application->dealership_company_name ?? 'your dealership';
            $title = 'Application Submitted!';
            $message = "Your dealer application for {$companyName} has been submitted and is pending review.";

            return $this->createNotification(
                $application->user_id,
                'dealer_application_submitted',
                $title,
                $message,
                [
                    'dealer_application_id' => $application->id,
                    'company_name' => $companyName,
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create dealer application submitted notification', [
                'application_id' => $application->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create dealer application approved notification
     */
    public function createDealerApplicationApprovedNotification($application): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('dealer_application_approved');
            if (!$notificationType) {
                throw new Exception('Notification type "dealer_application_approved" not found. Please run the seeder.');
            }

            $title = 'Dealer Application Approved!';
            $message = "Congratulations! Your dealer application has been approved. You can now post car listings and manage your dealership.";

            return $this->createNotification(
                $application->user_id,
                'dealer_application_approved',
                $title,
                $message,
                [
                    'dealer_application_id' => $application->id,
                    'approved_at' => $application->approved_at?->toISOString(),
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create dealer application approved notification', [
                'application_id' => $application->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create dealer application rejected notification
     */
    public function createDealerApplicationRejectedNotification($application, string $reason = null): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('dealer_application_rejected');
            if (!$notificationType) {
                throw new Exception('Notification type "dealer_application_rejected" not found. Please run the seeder.');
            }

            $title = 'Dealer Application Rejected';
            $reasonText = $reason ?? 'No reason provided';
            $message = "Your dealer application was not approved. Reason: {$reasonText}. Please review the requirements and submit a new application.";

            return $this->createNotification(
                $application->user_id,
                'dealer_application_rejected',
                $title,
                $message,
                [
                    'dealer_application_id' => $application->id,
                    'reason' => $reasonText,
                    'rejected_at' => $application->rejected_at?->toISOString(),
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create dealer application rejected notification', [
                'application_id' => $application->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create user created (welcome) notification
     */
    public function createUserCreatedNotification($user): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('user_created');
            if (!$notificationType) {
                throw new Exception('Notification type "user_created" not found. Please run the NotificationTypeSeeder.');
            }

            $userName = $user->full_name ?? 'there';
            $title = 'Welcome to Steren!';
            $message = "Welcome {$userName}! We're excited to have you join our community. Start exploring amazing cars or list your own!";

            return $this->createNotification(
                $user->id,
                'user_created',
                $title,
                $message,
                [
                    'user_id' => $user->id,
                    'user_name' => $userName,
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create user created notification', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}

