<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CarListing;
use App\Models\Carousel;
use App\Models\CarBrand;
use App\Models\BodyType;
use App\Models\AuthorizedAgency;
use App\Models\CarSubmission;
use App\Models\User;
use App\Models\Media;
use App\Observers\CarListingObserver;
use App\Observers\CarouselObserver;
use App\Observers\CarBrandObserver;
use App\Observers\BodyTypeObserver;
use App\Observers\AuthorizedAgencyObserver;
use App\Observers\CarSubmissionObserver;
use App\Observers\UserObserver;
use App\Observers\MediaObserver;
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
        // Register observers for media cleanup and cascade delete
        Media::observe(MediaObserver::class);
        CarListing::observe(CarListingObserver::class);
        Carousel::observe(CarouselObserver::class);
        CarBrand::observe(CarBrandObserver::class);
        BodyType::observe(BodyTypeObserver::class);
        AuthorizedAgency::observe(AuthorizedAgencyObserver::class);
        CarSubmission::observe(CarSubmissionObserver::class);
        User::observe(UserObserver::class);
    }
}
