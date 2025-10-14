<?php

namespace App\Observers;

use App\Models\CarListing;
use App\Services\AutoStudioService;

class CarListingObserver
{
    protected AutoStudioService $autoStudioService;

    public function __construct(AutoStudioService $autoStudioService)
    {
        $this->autoStudioService = $autoStudioService;
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
        // If listing became active and user is dealer, process it
        if ($carListing->wasChanged('status') && $carListing->status === 'active') {
            $this->autoStudioService->processNewDealerListing($carListing);
        }
    }
}
