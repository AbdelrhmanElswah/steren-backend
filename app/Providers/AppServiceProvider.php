<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CarListing;
use App\Observers\CarListingObserver;
use App\Services\UnifiedNotificationService;
use App\Services\DatabaseNotificationService;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind UnifiedNotificationService with optional Firebase
        $this->app->singleton(UnifiedNotificationService::class, function ($app) {
            $databaseService = $app->make(DatabaseNotificationService::class);
            
            // Firebase service is optional - only inject if enabled and configured
            $firebaseService = null;
            $firebaseEnabled = config('services.firebase.enabled', env('FIREBASE_ENABLED', false));
            
            if ($firebaseEnabled) {
                try {
                    $firebaseService = $app->make(FirebaseNotificationService::class);
                } catch (\Exception $e) {
                    Log::warning('Firebase service not available, continuing with database-only notifications', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return new UnifiedNotificationService($databaseService, $firebaseService);
        });
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
