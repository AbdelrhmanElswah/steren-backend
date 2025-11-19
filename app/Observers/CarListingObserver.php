<?php

namespace App\Observers;

use App\Models\CarListing;
use App\Services\AutoStudioService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class CarListingObserver
{
    protected AutoStudioService $autoStudioService;
    protected NotificationService $notificationService;

    public function __construct(
        AutoStudioService $autoStudioService,
        NotificationService $notificationService
    ) {
        $this->autoStudioService = $autoStudioService;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the CarListing "created" event.
     */
    public function created(CarListing $carListing): void
    {
        // Auto-process for dealers
        $this->autoStudioService->processNewDealerListing($carListing);
    }

    /**
     * Handle the CarListing "updated" event.
     */
    public function updated(CarListing $carListing): void
    {
        // TODO: Temporarily disabled - processNewDealerListing commented out for performance
        // This was causing additional database queries and updates during approve/reject operations
        // If listing became active and user is dealer, process it
        // if ($carListing->wasChanged('status') && $carListing->status === 'active') {
        //     $this->autoStudioService->processNewDealerListing($carListing);
        // }

        // TODO: Temporarily disabled - FCM notification sending commented out for performance
        // FCM (Firebase Cloud Messaging) sends push notifications to users' mobile devices
        // This was causing 30+ second timeouts due to synchronous HTTP requests to Firebase
        // Should be moved to a background job/queue for async processing
        
        // Send notification when listing is approved
        // if ($carListing->wasChanged('status') && $carListing->status === 'active') {
        //     try {
        //         $this->notificationService->sendListingApprovedNotification($carListing);
        //     } catch (\Exception $e) {
        //         Log::error('Failed to send listing approved notification', [
        //             'listing_id' => $carListing->id,
        //             'error' => $e->getMessage()
        //         ]);
        //     }
        // }

        // Send notification when listing is rejected
        // if ($carListing->wasChanged('status') && $carListing->status === 'rejected') {
        //     try {
        //         $this->notificationService->sendListingRejectedNotification($carListing);
        //     } catch (\Exception $e) {
        //         Log::error('Failed to send listing rejected notification', [
        //             'listing_id' => $carListing->id,
        //             'error' => $e->getMessage()
        //         ]);
        //     }
        // }

        // Send price drop alert if price decreased significantly
        if ($carListing->wasChanged('price') && $carListing->getOriginal('price') > $carListing->price) {
            $priceDropPercentage = (($carListing->getOriginal('price') - $carListing->price) / $carListing->getOriginal('price')) * 100;
            
            if ($priceDropPercentage >= 5) { // 5% or more price drop
                try {
                    // Get users who have this listing in their favorites
                    $userIds = $carListing->favorites()->pluck('user_id')->toArray();
                    
                    if (!empty($userIds)) {
                        $this->notificationService->sendPriceDropAlert(
                            $carListing,
                            $carListing->getOriginal('price'),
                            $carListing->price,
                            $userIds
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send price drop alert', [
                        'listing_id' => $carListing->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
