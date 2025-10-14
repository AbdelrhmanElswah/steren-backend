<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CarListing;
use App\Observers\CarListingObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        CarListing::observe(CarListingObserver::class);
    }
}
