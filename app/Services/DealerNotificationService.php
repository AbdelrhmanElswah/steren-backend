<?php

namespace App\Services;

use App\Models\DealerApplication;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class DealerNotificationService
{
    /**
     * Send notification when dealer application status changes
     */
    public function notifyApplicationStatusChange(DealerApplication $application)
    {
        $user = $application->user;
        
        $notificationData = $this->getNotificationDataForStatus($application);
        
        if ($notificationData) {
            $this->createNotification($user, $notificationData);
            $this->logStatusChange($application, $notificationData);
        }
    }

    /**
     * Send notification when dealer subscription is about to expire
     */
    public function notifySubscriptionExpiring(DealerApplication $application, int $daysUntilExpiration)
    {
        $user = $application->user;
        
        $notificationData = [
            'type' => 'dealer_subscription_expiring',
            'title' => 'Subscription Expires Soon',
            'message' => "Your dealer subscription expires in {$daysUntilExpiration} days. Renew now to keep your account active.",
            'data' => [
                'dealer_application_id' => $application->id,
                'days_until_expiration' => $daysUntilExpiration,
                'expires_at' => $application->expires_at,
                'subscription_plan' => $application->subscriptionPlan ? [
                    'id' => $application->subscriptionPlan->id,
                    'name' => $application->subscriptionPlan->name,
                    'price' => $application->subscriptionPlan->price,
                ] : null,
            ],
            'action_url' => '/account/subscription',
            'priority' => 'high',
        ];

        $this->createNotification($user, $notificationData);
        $this->logExpirationWarning($application, $daysUntilExpiration);
    }

    /**
     * Send notification when dealer subscription has expired
     */
    public function notifySubscriptionExpired(DealerApplication $application)
    {
        $user = $application->user;
        
        $notificationData = [
            'type' => 'dealer_subscription_expired',
            'title' => 'Subscription Has Expired',
            'message' => 'Your dealer subscription has expired. Renew now to reactivate your dealer account and continue posting cars.',
            'data' => [
                'dealer_application_id' => $application->id,
                'expires_at' => $application->expires_at,
                'subscription_plan' => $application->subscriptionPlan ? [
                    'id' => $application->subscriptionPlan->id,
                    'name' => $application->subscriptionPlan->name,
                    'price' => $application->subscriptionPlan->price,
                ] : null,
            ],
            'action_url' => '/account/subscription',
            'priority' => 'urgent',
        ];

        $this->createNotification($user, $notificationData);
        $this->logExpiration($application);
    }

    /**
     * Send notification when dealer application is approved and payment is completed
     */
    public function notifyDealerActivated(DealerApplication $application)
    {
        $user = $application->user;
        
        $notificationData = [
            'type' => 'dealer_activated',
            'title' => "You're Now a Verified Dealer!",
            'message' => 'Your account is active. Start posting cars and reach more buyers today.',
            'data' => [
                'dealer_application_id' => $application->id,
                'subscription_plan' => $application->subscriptionPlan ? [
                    'id' => $application->subscriptionPlan->id,
                    'name' => $application->subscriptionPlan->name,
                    'price' => $application->subscriptionPlan->price,
                ] : null,
                'expires_at' => $application->expires_at,
            ],
            'action_url' => '/sell',
            'priority' => 'high',
        ];

        $this->createNotification($user, $notificationData);
        $this->logActivation($application);
    }

    /**
     * Get notification data based on application status
     */
    private function getNotificationDataForStatus(DealerApplication $application): ?array
    {
        return match ($application->status) {
            'pending' => [
                'type' => 'dealer_application_submitted',
                'title' => 'Application Submitted',
                'message' => 'Your dealer application has been submitted and is pending review.',
                'data' => [
                    'dealer_application_id' => $application->id,
                    'status' => $application->status,
                ],
                'action_url' => '/account/dealer',
                'priority' => 'normal',
            ],
            'under_review' => [
                'type' => 'dealer_application_review',
                'title' => 'Application Under Review',
                'message' => 'Our team is currently reviewing your dealer application. You will be notified once a decision is made.',
                'data' => [
                    'dealer_application_id' => $application->id,
                    'status' => $application->status,
                ],
                'action_url' => '/account/dealer',
                'priority' => 'normal',
            ],
            'approved' => [
                'type' => 'dealer_application_approved',
                'title' => 'Application Approved!',
                'message' => 'Your application has been approved. Choose a plan to start posting and grow your dealership today.',
                'data' => [
                    'dealer_application_id' => $application->id,
                    'status' => $application->status,
                    'subscription_plan' => $application->subscriptionPlan ? [
                        'id' => $application->subscriptionPlan->id,
                        'name' => $application->subscriptionPlan->name,
                        'price' => $application->subscriptionPlan->price,
                    ] : null,
                ],
                'action_url' => '/account/subscription',
                'priority' => 'high',
            ],
            'rejected' => [
                'type' => 'dealer_application_rejected',
                'title' => 'Application Not Approved',
                'message' => $application->rejection_reason 
                    ? "Unfortunately, your application was not approved: {$application->rejection_reason}"
                    : 'We couldn\'t approve your dealer application at this time. You can contact support for more details.',
                'data' => [
                    'dealer_application_id' => $application->id,
                    'status' => $application->status,
                    'rejection_reason' => $application->rejection_reason,
                ],
                'action_url' => '/support',
                'priority' => 'normal',
            ],
            default => null,
        };
    }

    /**
     * Create notification for user
     */
    private function createNotification(User $user, array $notificationData): void
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => $notificationData['type'],
                'title' => $notificationData['title'],
                'message' => $notificationData['message'],
                'data' => $notificationData['data'] ?? null,
                'action_url' => $notificationData['action_url'] ?? null,
                'priority' => $notificationData['priority'] ?? 'normal',
                'read_at' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create dealer notification', [
                'user_id' => $user->id,
                'notification_data' => $notificationData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log status change for monitoring
     */
    private function logStatusChange(DealerApplication $application, array $notificationData): void
    {
        Log::info('Dealer application status changed', [
            'dealer_application_id' => $application->id,
            'user_id' => $application->user_id,
            'status' => $application->status,
            'notification_type' => $notificationData['type'],
            'reviewed_by' => $application->reviewed_by,
            'reviewed_at' => $application->reviewed_at,
        ]);
    }

    /**
     * Log expiration warning
     */
    private function logExpirationWarning(DealerApplication $application, int $daysUntilExpiration): void
    {
        Log::warning('Dealer subscription expiring soon', [
            'dealer_application_id' => $application->id,
            'user_id' => $application->user_id,
            'days_until_expiration' => $daysUntilExpiration,
            'expires_at' => $application->expires_at,
        ]);
    }

    /**
     * Log expiration
     */
    private function logExpiration(DealerApplication $application): void
    {
        Log::error('Dealer subscription expired', [
            'dealer_application_id' => $application->id,
            'user_id' => $application->user_id,
            'expires_at' => $application->expires_at,
        ]);
    }

    /**
     * Log activation
     */
    private function logActivation(DealerApplication $application): void
    {
        Log::info('Dealer account activated', [
            'dealer_application_id' => $application->id,
            'user_id' => $application->user_id,
            'subscription_plan_id' => $application->subscription_plan_id,
            'activated_at' => now(),
        ]);
    }

    /**
     * Check for expiring subscriptions and send notifications
     */
    public function checkExpiringSubscriptions(): void
    {
        $expiringApplications = DealerApplication::where('status', 'approved')
            ->where('payment_completed', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(3))
            ->with(['user', 'subscriptionPlan'])
            ->get();

        foreach ($expiringApplications as $application) {
            $daysUntilExpiration = $application->expires_at->diffInDays(now());
            
            if ($daysUntilExpiration <= 3 && $daysUntilExpiration > 0) {
                $this->notifySubscriptionExpiring($application, $daysUntilExpiration);
            }
        }
    }

    /**
     * Check for expired subscriptions and send notifications
     */
    public function checkExpiredSubscriptions(): void
    {
        $expiredApplications = DealerApplication::where('status', 'approved')
            ->where('payment_completed', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['user', 'subscriptionPlan'])
            ->get();

        foreach ($expiredApplications as $application) {
            $this->notifySubscriptionExpired($application);
        }
    }
}
