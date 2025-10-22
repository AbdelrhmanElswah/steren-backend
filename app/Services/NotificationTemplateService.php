<?php

namespace App\Services;

use App\Models\NotificationType;
use App\Models\User;
use App\Models\CarListing;
use App\Models\DealerApplication;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationTemplateService
{
    /**
     * Get notification templates for different scenarios
     */
    public function getTemplates(): array
    {
        return [
            'listing_approved' => [
                'title' => 'Your listing has been approved! 🎉',
                'body' => 'Great news! Your {{car_name}} listing is now live and visible to buyers.',
                'data' => [
                    'type' => 'listing_approved',
                    'action' => 'view_listing',
                    'listing_id' => '{{listing_id}}'
                ]
            ],
            'listing_rejected' => [
                'title' => 'Listing needs attention',
                'body' => 'Your {{car_name}} listing was not approved. Reason: {{reason}}',
                'data' => [
                    'type' => 'listing_rejected',
                    'action' => 'edit_listing',
                    'listing_id' => '{{listing_id}}'
                ]
            ],
            'dealer_application_approved' => [
                'title' => 'Welcome to our dealer network! 🚗',
                'body' => 'Congratulations! Your dealer application has been approved. You can now list vehicles.',
                'data' => [
                    'type' => 'dealer_approved',
                    'action' => 'view_dashboard'
                ]
            ],
            'dealer_application_rejected' => [
                'title' => 'Dealer application update',
                'body' => 'Your dealer application was not approved. Reason: {{reason}}',
                'data' => [
                    'type' => 'dealer_rejected',
                    'action' => 'reapply'
                ]
            ],
            'new_message' => [
                'title' => 'New message about {{car_name}}',
                'body' => '{{sender_name}} sent you a message about your {{car_name}} listing.',
                'data' => [
                    'type' => 'new_message',
                    'action' => 'view_messages',
                    'listing_id' => '{{listing_id}}'
                ]
            ],
            'payment_successful' => [
                'title' => 'Payment successful! 💳',
                'body' => 'Your payment of {{amount}} has been processed successfully.',
                'data' => [
                    'type' => 'payment_successful',
                    'action' => 'view_payments'
                ]
            ],
            'payment_failed' => [
                'title' => 'Payment failed',
                'body' => 'Your payment of {{amount}} could not be processed. Reason: {{reason}}',
                'data' => [
                    'type' => 'payment_failed',
                    'action' => 'retry_payment'
                ]
            ],
            'new_match' => [
                'title' => 'New car match found! 🔍',
                'body' => 'We found a {{car_name}} that matches your search "{{search_title}}".',
                'data' => [
                    'type' => 'new_match',
                    'action' => 'view_listing',
                    'listing_id' => '{{listing_id}}'
                ]
            ],
            'subscription_expiring' => [
                'title' => 'Subscription expiring soon',
                'body' => 'Your {{plan_name}} subscription expires in {{days_left}} days. Renew to continue listing vehicles.',
                'data' => [
                    'type' => 'subscription_expiring',
                    'action' => 'renew_subscription'
                ]
            ],
            'subscription_expired' => [
                'title' => 'Subscription expired',
                'body' => 'Your {{plan_name}} subscription has expired. Renew to continue listing vehicles.',
                'data' => [
                    'type' => 'subscription_expired',
                    'action' => 'renew_subscription'
                ]
            ]
        ];
    }

    /**
     * Process template with data
     */
    public function processTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    /**
     * Get listing approved notification data
     */
    public function getListingApprovedData(CarListing $listing): array
    {
        return [
            'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
            'listing_id' => $listing->id,
            'price' => $listing->formatted_price,
            'year' => $listing->year
        ];
    }

    /**
     * Get listing rejected notification data
     */
    public function getListingRejectedData(CarListing $listing, string $reason): array
    {
        return [
            'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
            'listing_id' => $listing->id,
            'reason' => $reason,
            'price' => $listing->formatted_price
        ];
    }

    /**
     * Get dealer application approved notification data
     */
    public function getDealerApprovedData(DealerApplication $application): array
    {
        return [
            'dealer_name' => $application->dealership_company_name,
            'application_id' => $application->id,
            'approved_at' => $application->approved_at?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get dealer application rejected notification data
     */
    public function getDealerRejectedData(DealerApplication $application, string $reason): array
    {
        return [
            'dealer_name' => $application->dealership_company_name,
            'application_id' => $application->id,
            'reason' => $reason,
            'rejected_at' => $application->rejected_at?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get new message notification data
     */
    public function getNewMessageData(User $sender, CarListing $listing): array
    {
        return [
            'sender_name' => $sender->name ?? $sender->email,
            'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
            'listing_id' => $listing->id,
            'sender_id' => $sender->id
        ];
    }

    /**
     * Get payment notification data
     */
    public function getPaymentData(float $amount, string $description = null): array
    {
        return [
            'amount' => number_format($amount, 2),
            'description' => $description ?? 'Payment processed',
            'currency' => 'USD'
        ];
    }

    /**
     * Get new match notification data
     */
    public function getNewMatchData(CarListing $listing, string $searchTitle): array
    {
        return [
            'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
            'listing_id' => $listing->id,
            'search_title' => $searchTitle,
            'price' => $listing->formatted_price,
            'year' => $listing->year
        ];
    }

    /**
     * Get subscription notification data
     */
    public function getSubscriptionData(string $planName, int $daysLeft = null): array
    {
        return [
            'plan_name' => $planName,
            'days_left' => $daysLeft,
            'expires_at' => now()->addDays($daysLeft ?? 0)->format('Y-m-d')
        ];
    }

    /**
     * Create notification type if it doesn't exist
     */
    public function createNotificationType(string $name, array $template): NotificationType
    {
        try {
            return NotificationType::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $template['display_name'] ?? ucwords(str_replace('_', ' ', $name)),
                    'description' => $template['description'] ?? '',
                    'default_template' => [
                        'title' => $template['title'],
                        'body' => $template['body']
                    ],
                    'is_active' => true
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to create notification type', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Initialize all notification types
     */
    public function initializeNotificationTypes(): array
    {
        $templates = $this->getTemplates();
        $created = [];

        foreach ($templates as $name => $template) {
            try {
                $notificationType = $this->createNotificationType($name, $template);
                $created[] = $notificationType;
            } catch (Exception $e) {
                Log::error("Failed to create notification type: {$name}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $created;
    }

    /**
     * Get notification type by name
     */
    public function getNotificationTypeByName(string $name): ?NotificationType
    {
        return NotificationType::where('name', $name)->first();
    }

    /**
     * Update notification template
     */
    public function updateTemplate(string $name, array $template): bool
    {
        try {
            $notificationType = $this->getNotificationTypeByName($name);
            if (!$notificationType) {
                return false;
            }

            $notificationType->update([
                'default_template' => [
                    'title' => $template['title'],
                    'body' => $template['body']
                ]
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to update notification template', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get personalized notification data for user
     */
    public function getPersonalizedData(User $user, string $type): array
    {
        $baseData = [
            'user_name' => $user->name ?? $user->email,
            'user_id' => $user->id
        ];

        switch ($type) {
            case 'welcome':
                return array_merge($baseData, [
                    'welcome_message' => 'Welcome to our platform!',
                    'first_name' => explode(' ', $user->name ?? 'User')[0]
                ]);

            case 'reminder':
                return array_merge($baseData, [
                    'reminder_type' => 'general',
                    'action_required' => false
                ]);

            default:
                return $baseData;
        }
    }
}
