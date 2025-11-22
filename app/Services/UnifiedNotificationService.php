<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\CarListing;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Unified Notification Service
 * 
 * This service orchestrates both database notifications (always active) 
 * and Firebase push notifications (optional, configurable).
 * 
 * Architecture:
 * - Database notifications: Always created (core system) - REQUIRED
 * - Firebase notifications: Optional, can be enabled via config - OPTIONAL
 * 
 * How to enable Firebase push notifications:
 * 1. Set FIREBASE_ENABLED=true in your .env file
 * 2. Configure Firebase credentials (FIREBASE_PROJECT_ID, etc.)
 * 3. Place google-services.json in storage/app/private/ or public/
 * 
 * When Firebase is disabled:
 * - All notifications are still saved to the database
 * - No push notifications are sent
 * - System works perfectly fine for in-app notifications
 * 
 * When Firebase is enabled:
 * - Database notifications are created (as always)
 * - Push notifications are sent via Firebase
 * - If Firebase fails, database notifications still work
 */
class UnifiedNotificationService
{
    protected DatabaseNotificationService $databaseService;
    protected ?FirebaseNotificationService $firebaseService;
    protected bool $firebaseEnabled;

    public function __construct(
        DatabaseNotificationService $databaseService,
        ?FirebaseNotificationService $firebaseService = null
    ) {
        $this->databaseService = $databaseService;
        $this->firebaseService = $firebaseService;
        $this->firebaseEnabled = $this->isFirebaseEnabled();
    }

    /**
     * Check if Firebase is enabled and configured
     */
    protected function isFirebaseEnabled(): bool
    {
        // Check if Firebase is enabled via config
        $enabled = config('services.firebase.enabled', env('FIREBASE_ENABLED', false));
        
        if (!$enabled) {
            return false;
        }

        // Check if Firebase service is available
        if (!$this->firebaseService) {
            return false;
        }

        // Check if required Firebase config exists
        $projectId = config('services.firebase.project_id') ?? env('FIREBASE_PROJECT_ID');
        $hasCredentials = file_exists(
            public_path(config('services.firebase.credentials_path', 'google-services.json'))
        ) || file_exists(
            storage_path('app/private/google-services.json')
        );

        return !empty($projectId) && $hasCredentials;
    }

    /**
     * Send notification to user(s) - creates database record and optionally sends push notification
     * 
     * @param array $userIds
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @param int|null $notificationTypeId
     * @return array
     */
    public function sendNotification(
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?int $notificationTypeId = null
    ): array {
        $results = [
            'database' => [],
            'firebase' => null,
            'success' => false,
        ];

        // Always create database notification (core system)
        try {
            $databaseNotifications = [];
            foreach ($userIds as $userId) {
                $notification = $this->databaseService->createNotification(
                    $userId,
                    $type,
                    $title,
                    $message,
                    $data
                );
                $databaseNotifications[] = $notification;
            }
            $results['database'] = $databaseNotifications;
            $results['success'] = true;

            Log::info('Database notifications created', [
                'user_ids' => $userIds,
                'type' => $type,
                'count' => count($databaseNotifications)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create database notifications', [
                'user_ids' => $userIds,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            // Don't throw - database notifications are critical
        }

        // Optionally send Firebase push notifications
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $firebaseResult = $this->firebaseService->sendToUsers(
                    $userIds,
                    $title,
                    $message,
                    $data,
                    $notificationTypeId
                );
                $results['firebase'] = $firebaseResult;

                Log::info('Firebase notifications sent', [
                    'user_ids' => $userIds,
                    'type' => $type,
                    'success' => $firebaseResult['success'] ?? false
                ]);

            } catch (Exception $e) {
                Log::warning('Firebase notification failed (non-critical)', [
                    'user_ids' => $userIds,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
                // Don't throw - Firebase is optional
                $results['firebase'] = [
                    'success' => false,
                    'message' => 'Firebase notification failed: ' . $e->getMessage()
                ];
            }
        } else {
            Log::debug('Firebase notifications skipped (disabled or not configured)', [
                'enabled' => $this->firebaseEnabled,
                'has_service' => !is_null($this->firebaseService)
            ]);
        }

        return $results;
    }

    /**
     * Create listing created notification
     */
    public function createListingCreatedNotification(CarListing $listing): array
    {
        // Always create database notification
        $notification = $this->databaseService->createListingCreatedNotification($listing);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $carName = $listing->title ?? 'Your listing';
                if ($listing->brand && $listing->carModel) {
                    $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                    if ($listing->year) {
                        $carName .= ' ' . $listing->year;
                    }
                }

                $title = 'Listing Created Successfully!';
                $message = "Your {$carName} listing has been created and is pending approval.";

                $notificationType = \App\Models\NotificationType::findByName('listing_created');
                if ($notificationType) {
                    $firebaseResult = $this->firebaseService->sendToUser(
                        $listing->user_id,
                        $title,
                        $message,
                        [
                            'car_listing_id' => $listing->id,
                            'car_name' => $carName,
                            'type' => 'listing_created',
                        ],
                        $notificationType->id
                    );

                    $results['firebase'] = $firebaseResult;
                }

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for listing created (non-critical)', [
                    'listing_id' => $listing->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create listing approved notification
     */
    public function createListingApprovedNotification(CarListing $listing): array
    {
        // Always create database notification
        $notification = $this->databaseService->createListingApprovedNotification($listing);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $carName = $listing->title ?? 'Your listing';
                if ($listing->brand && $listing->carModel) {
                    $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                    if ($listing->year) {
                        $carName .= ' ' . $listing->year;
                    }
                }

                $title = 'Listing Approved!';
                $message = "Great news! Your {$carName} listing has been approved and is now live.";

                $notificationType = \App\Models\NotificationType::findByName('listing_approved');

                $firebaseResult = $this->firebaseService->sendToUser(
                    $listing->user_id,
                    $title,
                    $message,
                    [
                        'car_listing_id' => $listing->id,
                        'car_name' => $carName,
                        'type' => 'listing_approved',
                    ],
                    $notificationType->id
                );

                $results['firebase'] = $firebaseResult;

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for listing approval (non-critical)', [
                    'listing_id' => $listing->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create listing rejected notification
     */
    public function createListingRejectedNotification(CarListing $listing, string $reason = null): array
    {
        // Always create database notification
        $notification = $this->databaseService->createListingRejectedNotification($listing, $reason);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $carName = $listing->title ?? 'Your listing';
                if ($listing->brand && $listing->carModel) {
                    $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                    if ($listing->year) {
                        $carName .= ' ' . $listing->year;
                    }
                }

                $title = 'Listing Rejected';
                $reasonText = $reason ?? 'No reason provided';
                $message = "Your {$carName} listing was not approved. Reason: {$reasonText}.";

                $notificationType = \App\Models\NotificationType::findByName('listing_rejected');

                $firebaseResult = $this->firebaseService->sendToUser(
                    $listing->user_id,
                    $title,
                    $message,
                    [
                        'car_listing_id' => $listing->id,
                        'car_name' => $carName,
                        'reason' => $reasonText,
                        'type' => 'listing_rejected',
                    ],
                    $notificationType->id
                );

                $results['firebase'] = $firebaseResult;

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for listing rejection (non-critical)', [
                    'listing_id' => $listing->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->databaseService->getUnreadCount($userId);
    }

    /**
     * Create dealer application submitted notification
     */
    public function createDealerApplicationSubmittedNotification($application): array
    {
        // Always create database notification
        $notification = $this->databaseService->createDealerApplicationSubmittedNotification($application);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $companyName = $application->dealership_company_name ?? 'your dealership';
                $title = 'Application Submitted!';
                $message = "Your dealer application for {$companyName} has been submitted and is pending review.";

                $notificationType = \App\Models\NotificationType::findByName('dealer_application_submitted');
                if ($notificationType) {
                    $firebaseResult = $this->firebaseService->sendToUser(
                        $application->user_id,
                        $title,
                        $message,
                        [
                            'dealer_application_id' => $application->id,
                            'company_name' => $companyName,
                            'type' => 'dealer_application_submitted',
                        ],
                        $notificationType->id
                    );

                    $results['firebase'] = $firebaseResult;
                }

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for dealer application submitted (non-critical)', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create dealer application approved notification
     */
    public function createDealerApplicationApprovedNotification($application): array
    {
        // Always create database notification
        $notification = $this->databaseService->createDealerApplicationApprovedNotification($application);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $title = 'Dealer Application Approved!';
                $message = "Congratulations! Your dealer application has been approved. You can now post car listings and manage your dealership.";

                $notificationType = \App\Models\NotificationType::findByName('dealer_application_approved');

                $firebaseResult = $this->firebaseService->sendToUser(
                    $application->user_id,
                    $title,
                    $message,
                    [
                        'dealer_application_id' => $application->id,
                        'type' => 'dealer_application_approved',
                    ],
                    $notificationType->id
                );

                $results['firebase'] = $firebaseResult;

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for dealer application approval (non-critical)', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create dealer application rejected notification
     */
    public function createDealerApplicationRejectedNotification($application, string $reason = null): array
    {
        // Always create database notification
        $notification = $this->databaseService->createDealerApplicationRejectedNotification($application, $reason);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $title = 'Dealer Application Rejected';
                $reasonText = $reason ?? 'No reason provided';
                $message = "Your dealer application was not approved. Reason: {$reasonText}.";

                $notificationType = \App\Models\NotificationType::findByName('dealer_application_rejected');

                $firebaseResult = $this->firebaseService->sendToUser(
                    $application->user_id,
                    $title,
                    $message,
                    [
                        'dealer_application_id' => $application->id,
                        'reason' => $reasonText,
                        'type' => 'dealer_application_rejected',
                    ],
                    $notificationType->id
                );

                $results['firebase'] = $firebaseResult;

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for dealer application rejection (non-critical)', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create user created (welcome) notification
     */
    public function createUserCreatedNotification($user): array
    {
        // Always create database notification
        $notification = $this->databaseService->createUserCreatedNotification($user);

        $results = [
            'database' => $notification,
            'firebase' => null,
            'success' => true,
        ];

        // Optionally send Firebase push notification
        if ($this->firebaseEnabled && $this->firebaseService) {
            try {
                $userName = $user->full_name ?? 'there';
                $title = 'Welcome to Steren!';
                $message = "Welcome {$userName}! We're excited to have you join our community. Start exploring amazing cars or list your own!";

                $notificationType = \App\Models\NotificationType::findByName('user_created');
                if ($notificationType) {
                    $firebaseResult = $this->firebaseService->sendToUser(
                        $user->id,
                        $title,
                        $message,
                        [
                            'user_id' => $user->id,
                            'user_name' => $userName,
                            'type' => 'user_created',
                        ],
                        $notificationType->id
                    );

                    $results['firebase'] = $firebaseResult;
                }

            } catch (Exception $e) {
                Log::warning('Firebase push notification failed for user created (non-critical)', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Check if Firebase is enabled
     */
    public function isFirebaseActive(): bool
    {
        return $this->firebaseEnabled;
    }
}

