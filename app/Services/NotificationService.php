<?php

namespace App\Services;

use App\Models\NotificationType;
use App\Models\User;
use App\Models\CarListing;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    use NotificationTrait;

    /**
     * Send listing approval notification
     */
    public function sendListingApprovedNotification(CarListing $listing): string
    {
        try {
            $notificationType = NotificationType::where('name', 'listing_approved')->first();
            if (!$notificationType) {
                throw new \Exception('Listing approved notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id,
                'price' => $listing->formatted_price
            ];

            return $this->sendTemplateNotification(
                [$listing->user_id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send listing approved notification', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send listing rejection notification
     */
    public function sendListingRejectedNotification(CarListing $listing, string $reason = null): string
    {
        try {
            $notificationType = NotificationType::where('name', 'listing_rejected')->first();
            if (!$notificationType) {
                throw new \Exception('Listing rejected notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id,
                'reason' => $reason ?? 'Please check your listing details and try again'
            ];

            return $this->sendTemplateNotification(
                [$listing->user_id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send listing rejected notification', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send price drop alert notification
     */
    public function sendPriceDropAlert(CarListing $listing, float $oldPrice, float $newPrice, array $userIds): string
    {
        try {
            $notificationType = NotificationType::where('name', 'price_drop')->first();
            if (!$notificationType) {
                throw new \Exception('Price drop notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id,
                'old_price' => number_format($oldPrice),
                'new_price' => number_format($newPrice),
                'price_difference' => number_format($oldPrice - $newPrice)
            ];

            return $this->sendTemplateNotification(
                $userIds,
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send price drop alert', [
                'listing_id' => $listing->id,
                'user_ids' => $userIds,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send new match notification for saved searches
     */
    public function sendNewMatchNotification(CarListing $listing, array $userIds, string $searchTitle): string
    {
        try {
            $notificationType = NotificationType::where('name', 'new_match')->first();
            if (!$notificationType) {
                throw new \Exception('New match notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id,
                'search_title' => $searchTitle,
                'price' => $listing->formatted_price,
                'year' => $listing->year
            ];

            return $this->sendTemplateNotification(
                $userIds,
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send new match notification', [
                'listing_id' => $listing->id,
                'user_ids' => $userIds,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send message received notification
     */
    public function sendMessageReceivedNotification(int $userId, string $senderName, string $carName): string
    {
        try {
            $notificationType = NotificationType::where('name', 'message_received')->first();
            if (!$notificationType) {
                throw new \Exception('Message received notification type not found');
            }

            $templateData = [
                'car_name' => $carName,
                'sender_name' => $senderName
            ];

            return $this->sendTemplateNotification(
                [$userId],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send message received notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send offer received notification
     */
    public function sendOfferReceivedNotification(int $userId, float $offerAmount, string $carName, int $offerId): string
    {
        try {
            $notificationType = NotificationType::where('name', 'offer_received')->first();
            if (!$notificationType) {
                throw new \Exception('Offer received notification type not found');
            }

            $templateData = [
                'car_name' => $carName,
                'offer_amount' => number_format($offerAmount),
                'offer_id' => $offerId
            ];

            return $this->sendTemplateNotification(
                [$userId],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send offer received notification', [
                'user_id' => $userId,
                'offer_id' => $offerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send listing expiring notification
     */
    public function sendListingExpiringNotification(CarListing $listing, int $daysLeft): string
    {
        try {
            $notificationType = NotificationType::where('name', 'listing_expiring')->first();
            if (!$notificationType) {
                throw new \Exception('Listing expiring notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id,
                'days' => $daysLeft
            ];

            return $this->sendTemplateNotification(
                [$listing->user_id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send listing expiring notification', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send boost activated notification
     */
    public function sendBoostActivatedNotification(CarListing $listing, int $durationDays): string
    {
        try {
            $notificationType = NotificationType::where('name', 'boost_activated')->first();
            if (!$notificationType) {
                throw new \Exception('Boost activated notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id,
                'duration' => $durationDays . ' days'
            ];

            return $this->sendTemplateNotification(
                [$listing->user_id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send boost activated notification', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send boost expired notification
     */
    public function sendBoostExpiredNotification(CarListing $listing): string
    {
        try {
            $notificationType = NotificationType::where('name', 'boost_expired')->first();
            if (!$notificationType) {
                throw new \Exception('Boost expired notification type not found');
            }

            $templateData = [
                'car_name' => $listing->carBrand->name . ' ' . $listing->carModel->name,
                'listing_id' => $listing->id
            ];

            return $this->sendTemplateNotification(
                [$listing->user_id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send boost expired notification', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send subscription expiring notification
     */
    public function sendSubscriptionExpiringNotification(User $user, int $daysLeft): string
    {
        try {
            $notificationType = NotificationType::where('name', 'subscription_expiring')->first();
            if (!$notificationType) {
                throw new \Exception('Subscription expiring notification type not found');
            }

            $subscription = $user->activeSubscription;
            $templateData = [
                'plan_name' => $subscription?->subscriptionPlan?->name ?? 'Your subscription',
                'days' => $daysLeft
            ];

            return $this->sendTemplateNotification(
                [$user->id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send subscription expiring notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send payment successful notification
     */
    public function sendPaymentSuccessfulNotification(User $user, float $amount, string $description = null): string
    {
        try {
            $notificationType = NotificationType::where('name', 'payment_successful')->first();
            if (!$notificationType) {
                throw new \Exception('Payment successful notification type not found');
            }

            $templateData = [
                'amount' => number_format($amount),
                'description' => $description ?? 'Payment processed successfully'
            ];

            return $this->sendTemplateNotification(
                [$user->id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment successful notification', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send payment failed notification
     */
    public function sendPaymentFailedNotification(User $user, float $amount, string $reason = null): string
    {
        try {
            $notificationType = NotificationType::where('name', 'payment_failed')->first();
            if (!$notificationType) {
                throw new \Exception('Payment failed notification type not found');
            }

            $templateData = [
                'amount' => number_format($amount),
                'reason' => $reason ?? 'Payment could not be processed'
            ];

            return $this->sendTemplateNotification(
                [$user->id],
                $notificationType->id,
                $templateData
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed notification', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(
        array $userIds,
        string $title,
        string $message,
        int $notificationTypeId,
        array $data = []
    ): string {
        try {
            return $this->sendNotification(
                $userIds,
                $title,
                $message,
                $userIds[0] ?? 1,
                $notificationTypeId,
                $data
            );
        } catch (\Exception $e) {
            Log::error('Failed to send custom notification', [
                'user_ids' => $userIds,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
