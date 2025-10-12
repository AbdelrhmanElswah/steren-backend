<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('odoo')->middleware('odoo.tracking')->group(function () {
    Route::post('car-listings', [App\Http\Controllers\Api\Odoo\CarListingController::class, 'store']);
    Route::post('car-listings/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarListingController::class, 'updateByOdooId']);
    Route::post('car-listings/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarListingController::class, 'deleteByOdooId']);
    Route::post('car-listings/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarListingController::class, 'restoreByOdooId']);
    
    Route::post('car-submissions', [App\Http\Controllers\Api\Odoo\CarSubmissionController::class, 'store']);
    Route::post('car-submissions/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarSubmissionController::class, 'updateByOdooId']);
    Route::post('car-submissions/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarSubmissionController::class, 'deleteByOdooId']);
    Route::post('car-submissions/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarSubmissionController::class, 'restoreByOdooId']);
    
    Route::post('car-inspections', [App\Http\Controllers\Api\Odoo\CarInspectionController::class, 'store']);
    Route::post('car-inspections/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarInspectionController::class, 'updateByOdooId']);
    Route::post('car-inspections/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarInspectionController::class, 'deleteByOdooId']);
    Route::post('car-inspections/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarInspectionController::class, 'restoreByOdooId']);
    
    Route::post('carfax-reports', [App\Http\Controllers\Api\Odoo\CarfaxReportController::class, 'store']);
    Route::post('carfax-reports/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarfaxReportController::class, 'updateByOdooId']);
    Route::post('carfax-reports/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarfaxReportController::class, 'deleteByOdooId']);
    Route::post('carfax-reports/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarfaxReportController::class, 'restoreByOdooId']);
    
    Route::post('cities', [App\Http\Controllers\Api\Odoo\CityController::class, 'store']);
    Route::post('cities/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CityController::class, 'updateByOdooId']);
    Route::post('cities/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CityController::class, 'deleteByOdooId']);
    Route::post('cities/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CityController::class, 'restoreByOdooId']);
    
    Route::post('car-brands', [App\Http\Controllers\Api\Odoo\CarBrandController::class, 'store']);
    Route::post('car-brands/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarBrandController::class, 'updateByOdooId']);
    Route::post('car-brands/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarBrandController::class, 'deleteByOdooId']);
    Route::post('car-brands/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarBrandController::class, 'restoreByOdooId']);
    
    Route::post('carousels', [App\Http\Controllers\Api\Odoo\CarouselController::class, 'store']);
    Route::post('carousels/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarouselController::class, 'updateByOdooId']);
    Route::post('carousels/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarouselController::class, 'deleteByOdooId']);
    Route::post('carousels/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarouselController::class, 'restoreByOdooId']);
    
    Route::post('car-models', [App\Http\Controllers\Api\Odoo\CarModelController::class, 'store']);
    Route::post('car-models/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\CarModelController::class, 'updateByOdooId']);
    Route::post('car-models/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\CarModelController::class, 'deleteByOdooId']);
    Route::post('car-models/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\CarModelController::class, 'restoreByOdooId']);
    
    Route::post('locations', [App\Http\Controllers\Api\Odoo\LocationController::class, 'store']);
    Route::post('locations/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\LocationController::class, 'updateByOdooId']);
    Route::post('locations/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\LocationController::class, 'deleteByOdooId']);
    Route::post('locations/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\LocationController::class, 'restoreByOdooId']);
    
    Route::post('studios', [App\Http\Controllers\Api\Odoo\StudioController::class, 'store']);
    Route::post('studios/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\StudioController::class, 'updateByOdooId']);
    Route::post('studios/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\StudioController::class, 'deleteByOdooId']);
    Route::post('studios/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\StudioController::class, 'restoreByOdooId']);
    
    Route::post('users', [App\Http\Controllers\Api\Odoo\UserController::class, 'store']);
    Route::post('users/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\UserController::class, 'updateByOdooId']);
    Route::post('users/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\UserController::class, 'deleteByOdooId']);
    Route::post('users/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\UserController::class, 'restoreByOdooId']);
    
    Route::post('body-types', [App\Http\Controllers\Api\Odoo\BodyTypeController::class, 'store']);
    Route::post('body-types/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\BodyTypeController::class, 'updateByOdooId']);
    Route::post('body-types/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\BodyTypeController::class, 'deleteByOdooId']);
    Route::post('body-types/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\BodyTypeController::class, 'restoreByOdooId']);
    
    Route::post('subscription-plans', [App\Http\Controllers\Api\Odoo\SubscriptionPlanController::class, 'store']);
    Route::post('subscription-plans/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\SubscriptionPlanController::class, 'updateByOdooId']);
    Route::post('subscription-plans/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\SubscriptionPlanController::class, 'deleteByOdooId']);
    Route::post('subscription-plans/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\SubscriptionPlanController::class, 'restoreByOdooId']);
    
    Route::post('budgets', [App\Http\Controllers\Api\Odoo\BudgetController::class, 'store']);
    Route::post('budgets/odoo/{odooId}/update', [App\Http\Controllers\Api\Odoo\BudgetController::class, 'updateByOdooId']);
    Route::post('budgets/odoo/{odooId}/delete', [App\Http\Controllers\Api\Odoo\BudgetController::class, 'deleteByOdooId']);
    Route::post('budgets/odoo/{odooId}/restore', [App\Http\Controllers\Api\Odoo\BudgetController::class, 'restoreByOdooId']);
});

// Car Listing API Routes - Protected with Sanctum
Route::prefix('car-listings')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [App\Http\Controllers\Api\CarListingApiController::class, 'store']);
});

// Listing Promotion API Routes - Public (for pricing info)
Route::prefix('listing-promotions')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\ListingPromotionController::class, 'index']);
    Route::get('/pricing', [App\Http\Controllers\Api\ListingPromotionController::class, 'pricing']);
    Route::get('/default', [App\Http\Controllers\Api\ListingPromotionController::class, 'default']);
    Route::get('/compare', [App\Http\Controllers\Api\ListingPromotionController::class, 'compare']);
    Route::get('/{id}', [App\Http\Controllers\Api\ListingPromotionController::class, 'show']);
});

// Odoo Data Fetch Routes (GET requests to fetch from regular database)
Route::prefix('odoo')->group(function () {
    Route::get('brands', [App\Http\Controllers\Api\Odoo\CarBrandController::class, 'getBrands']);
    Route::get('body-types', [App\Http\Controllers\Api\Odoo\BodyTypeController::class, 'getBodyTypes']);
    Route::get('cities', [App\Http\Controllers\Api\Odoo\CityController::class, 'getCities']);
    Route::get('car-models', [App\Http\Controllers\Api\Odoo\CarModelController::class, 'getCarModels']);
    Route::get('locations', [App\Http\Controllers\Api\Odoo\LocationController::class, 'getLocations']);
    Route::get('studios', [App\Http\Controllers\Api\Odoo\StudioController::class, 'getStudios']);
    Route::get('subscription-plans', [App\Http\Controllers\Api\Odoo\SubscriptionPlanController::class, 'getSubscriptionPlans']);
    Route::get('budgets', [App\Http\Controllers\Api\Odoo\BudgetController::class, 'getBudgets']);
});

// Client Routes
Route::prefix('client')->group(function () {
    Route::post('create', [App\Http\Controllers\Api\Client\ClientController::class, 'create']);
    Route::get('partner/{partnerId}', [App\Http\Controllers\Api\Client\ClientController::class, 'getByPartnerId']);
});

// Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\Auth\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login']);
    Route::post('/send-otp', [App\Http\Controllers\Auth\AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [App\Http\Controllers\Auth\AuthController::class, 'verifyOtp'])->middleware('auth:sanctum');
    Route::post('/social-login', [App\Http\Controllers\Auth\SocialAuthController::class, 'socialLogin']);
    Route::post('/password/forgot', [App\Http\Controllers\Auth\AuthController::class, 'startPasswordReset']);
    Route::post('/password/reset', [App\Http\Controllers\Auth\AuthController::class, 'completePasswordReset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [App\Http\Controllers\Auth\AuthController::class, 'me']);
        Route::post('/logout', [App\Http\Controllers\Auth\AuthController::class, 'logout']);
        Route::post('/logout-all', [App\Http\Controllers\Auth\AuthController::class, 'logoutAll']);
        
        // Partner/Odoo integration routes
        Route::get('/partner/info', [App\Http\Controllers\Auth\AuthController::class, 'getPartnerInfo']);
        Route::put('/partner/update', [App\Http\Controllers\Auth\AuthController::class, 'updatePartnerInfo']);
    });
});

// Car Damage Routes - GET only
Route::prefix('car-damages')->group(function () {
    Route::get('/car-parts', [App\Http\Controllers\Data\CarDamageController::class, 'getCarParts']);
    Route::get('/statistics/{carListingId}', [App\Http\Controllers\Data\CarDamageController::class, 'getDamageStatistics']);
    Route::get('/{carListingId}', [App\Http\Controllers\Data\CarDamageController::class, 'getCarDamage']);
});

// Report Routes - GET only
Route::prefix('reports')->group(function () {
    Route::get('/reasons', [App\Http\Controllers\Data\ReportController::class, 'getReportReasons']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [App\Http\Controllers\Data\ReportController::class, 'getUserReports']);
        Route::get('/{reportId}', [App\Http\Controllers\Data\ReportController::class, 'getReportDetails']);
        Route::get('/statistics/admin', [App\Http\Controllers\Data\ReportController::class, 'getReportStatistics']);
    });
});

// Car Inspection Routes - GET only
Route::prefix('inspections')->group(function () {
    Route::get('/statistics', [App\Http\Controllers\Data\CarInspectionController::class, 'getInspectionStatistics']);
    Route::get('/{carListingId}', [App\Http\Controllers\Data\CarInspectionController::class, 'getCarInspection']);
    Route::get('/inspector/{inspectorId}', [App\Http\Controllers\Data\CarInspectionController::class, 'getInspectionsByInspector']);
});

// Carfax Report Routes - GET only
Route::prefix('carfax')->group(function () {
    Route::get('/statistics', [App\Http\Controllers\Data\CarfaxReportController::class, 'getCarfaxStatistics']);
    Route::get('/vin/{vin}', [App\Http\Controllers\Data\CarfaxReportController::class, 'getCarfaxByVin']);
    Route::get('/{carListingId}', [App\Http\Controllers\Data\CarfaxReportController::class, 'getCarfaxReport']);
    Route::get('/search', [App\Http\Controllers\Data\CarfaxReportController::class, 'searchCarfaxReports']);
});

// Car Listing Routes - GET only
Route::prefix('car-listings')->group(function () {
    Route::get('/', [App\Http\Controllers\Data\CarListingController::class, 'getCarListings']);
    Route::get('/filter-options', [App\Http\Controllers\Data\CarListingController::class, 'getFilterOptions']);
    Route::get('/{id}', [App\Http\Controllers\Data\CarListingController::class, 'getCarListingDetails']);
    Route::get('/trims/{modelId}', [App\Http\Controllers\Data\CarListingController::class, 'getCarTrims']);
    Route::get('/news', [App\Http\Controllers\Data\CarListingController::class, 'getNewsArticles']);
});

// Contact Methods Routes - GET only
Route::prefix('contact-methods')->group(function () {
    Route::get('/', [App\Http\Controllers\Data\ContactMethodController::class, 'getContactMethods']);
});
Route::prefix('carousels')->group(function () {
    Route::get('/', [App\Http\Controllers\Data\CarouselController::class, 'getCarousels']);
    Route::get('/all', [App\Http\Controllers\Data\CarouselController::class, 'getAllCarousels']);
    Route::get('/{id}', [App\Http\Controllers\Data\CarouselController::class, 'getCarouselById']);
});

// Data Options Routes - GET only (Separated by category)
Route::prefix('options')->group(function () {
    // Car-related options
    Route::get('/car-brands', [App\Http\Controllers\Data\CarBrandOptionsController::class, 'getCarBrandOptions']);
    Route::get('/car-models', [App\Http\Controllers\Data\CarModelOptionsController::class, 'getCarModelOptions']);
    Route::get('/body-types', [App\Http\Controllers\Data\BodyTypeOptionsController::class, 'getBodyTypeOptions']);
    Route::get('/fuel-types', [App\Http\Controllers\Data\FuelTypeOptionsController::class, 'getFuelTypeOptions']);
    Route::get('/transmissions', [App\Http\Controllers\Data\TransmissionOptionsController::class, 'getTransmissionOptions']);
    Route::get('/drive-types', [App\Http\Controllers\Data\DriveTypeOptionsController::class, 'getDriveTypeOptions']);
    Route::get('/exterior-colors', [App\Http\Controllers\Data\ExteriorColorOptionsController::class, 'getExteriorColorOptions']);
    Route::get('/car-conditions', [App\Http\Controllers\Data\CarConditionOptionsController::class, 'getCarConditionOptions']);
    Route::get('/condition-types', [App\Http\Controllers\Data\ConditionTypeOptionsController::class, 'getConditionTypeOptions']);
    
    // Location options
    Route::get('/cities', [App\Http\Controllers\Data\CityOptionsController::class, 'getCityOptions']);
    
    // Status options
    Route::get('/statuses', [App\Http\Controllers\Data\StatusOptionsController::class, 'getStatusOptions']);
    
    // Promotion options
    Route::get('/promotion-types', [App\Http\Controllers\Data\PromotionTypeOptionsController::class, 'getPromotionTypeOptions']);
    
    // Report options
    Route::get('/report-reasons', [App\Http\Controllers\Data\ReportReasonOptionsController::class, 'getReportReasonOptions']);
});

// Watch List Routes - GET only
Route::prefix('watch-list')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [App\Http\Controllers\Data\WatchListController::class, 'getWatchList']);
    Route::get('/search-history', [App\Http\Controllers\Data\WatchListController::class, 'getSearchHistory']);
    Route::get('/sort-options', [App\Http\Controllers\Data\WatchListController::class, 'getSortOptions']);
});

// Account Routes - GET only
Route::prefix('account')->middleware('auth:sanctum')->group(function () {
    Route::get('/info', [App\Http\Controllers\Data\AccountController::class, 'getAccountInfo']);
    Route::get('/analytics', [App\Http\Controllers\Data\AccountController::class, 'getAnalytics']);
    Route::get('/my-ads', [App\Http\Controllers\Data\AccountController::class, 'getMyAds']);
    Route::get('/wallet', [App\Http\Controllers\Data\AccountController::class, 'getWallet']);
    Route::get('/wallet/transactions', [App\Http\Controllers\Data\AccountController::class, 'getWalletTransactions']);
    Route::get('/payment-methods', [App\Http\Controllers\Data\AccountController::class, 'getPaymentMethods']);
    Route::get('/my-subscription', [App\Http\Controllers\Data\AccountController::class, 'getMySubscription']);
    Route::get('/language-options', [App\Http\Controllers\Data\AccountController::class, 'getLanguageOptions']);
    Route::get('/theme-options', [App\Http\Controllers\Data\AccountController::class, 'getThemeOptions']);
    Route::get('/social-auth-options', [App\Http\Controllers\Data\AccountController::class, 'getSocialAuthOptions']);
    
    // Device Permissions Routes
    Route::get('/device-permissions', [App\Http\Controllers\Api\DevicePermissionsController::class, 'index']);
    Route::put('/device-permissions', [App\Http\Controllers\Api\DevicePermissionsController::class, 'updateMultiple']);
    Route::put('/device-permissions/{permission}', [App\Http\Controllers\Api\DevicePermissionsController::class, 'updatePermission']);
    Route::post('/device-permissions/reset', [App\Http\Controllers\Api\DevicePermissionsController::class, 'reset']);
});

// Analytics Routes - GET only
Route::prefix('analytics')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', [App\Http\Controllers\Data\AnalyticsController::class, 'getUserAnalytics']);
    Route::get('/car-listing/{carListingId}', [App\Http\Controllers\Data\AnalyticsController::class, 'getCarListingAnalytics']);
    Route::get('/boost-options', [App\Http\Controllers\Data\AnalyticsController::class, 'getBoostOptions']);
    Route::get('/my-ads', [App\Http\Controllers\Data\AnalyticsController::class, 'getMyAds']);
});

// Analytics Tracking Routes - POST only
Route::prefix('track')->group(function () {
    Route::post('/car-listing/{carListingId}/view', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackView']);
    Route::post('/car-listing/{carListingId}/share', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackShare']);
    Route::post('/car-listing/{carListingId}/call-tap', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackCallTap']);
    Route::post('/car-listing/{carListingId}/whatsapp-click', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackWhatsAppClick']);
    Route::post('/car-listing/{carListingId}/sms-click', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackSmsClick']);
    Route::post('/car-listing/{carListingId}/email-click', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackEmailClick']);
    Route::post('/car-listing/{carListingId}/watchlist-save', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackWatchlistSave']);
    Route::post('/car-listing/{carListingId}/favorite', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackFavorite']);
    Route::post('/car-listing/bulk', [App\Http\Controllers\Api\AnalyticsTrackingController::class, 'trackBulk']);
});

// Payment Processing Routes - GET only
Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::get('/status/{paymentId}', [App\Http\Controllers\Data\PaymentController::class, 'getPaymentStatus']);
});

// Subscription Management Routes
Route::prefix('subscriptions')->middleware('auth:sanctum')->group(function () {
    Route::get('/plans', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'getPlans']);
    Route::get('/current', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'getCurrentSubscription']);
    Route::get('/limits', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'getLimits']);
    Route::get('/history', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'getHistory']);
    Route::post('/subscribe', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'subscribe']);
    Route::post('/cancel', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'cancel']);
    Route::post('/renew', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'renew']);
    Route::post('/change-plan', [App\Http\Controllers\Api\Subscription\SubscriptionController::class, 'changePlan']);
});

// Dealer Application Routes
Route::prefix('dealer')->middleware('auth:sanctum')->group(function () {
    // Application Management
    Route::get('/status', [App\Http\Controllers\Api\Dealer\DealerApplicationController::class, 'status']);
    Route::get('/subscription-plans', [App\Http\Controllers\Api\Dealer\DealerApplicationController::class, 'subscriptionPlans']);
    Route::post('/application', [App\Http\Controllers\Api\Dealer\DealerApplicationController::class, 'store']);
    Route::get('/application/{id}', [App\Http\Controllers\Api\Dealer\DealerApplicationController::class, 'show']);
    Route::put('/application/{id}', [App\Http\Controllers\Api\Dealer\DealerApplicationController::class, 'update']);
    Route::delete('/application/{id}', [App\Http\Controllers\Api\Dealer\DealerApplicationController::class, 'destroy']);
    
    // Subscription Management
    Route::get('/subscription/current', [App\Http\Controllers\Api\Dealer\DealerSubscriptionController::class, 'current']);
    Route::get('/subscription/plans', [App\Http\Controllers\Api\Dealer\DealerSubscriptionController::class, 'plans']);
    Route::put('/subscription/update-plan', [App\Http\Controllers\Api\Dealer\DealerSubscriptionController::class, 'updatePlan']);
    Route::post('/subscription/complete-payment', [App\Http\Controllers\Api\Dealer\DealerSubscriptionController::class, 'completePayment']);
    Route::post('/subscription/renew', [App\Http\Controllers\Api\Dealer\DealerSubscriptionController::class, 'renew']);
    Route::get('/subscription/billing-history', [App\Http\Controllers\Api\Dealer\DealerSubscriptionController::class, 'billingHistory']);
});

// Dealer Verification Routes
Route::prefix('verification')->group(function () {
    // Public route for Odoo webhook
    Route::post('/update-status', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'updateStatus']);
    
    // Protected routes for authenticated users
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/submit', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'submit']);
        Route::get('/status', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'status']);
    });
    
    Route::get('/subscription-plans', [App\Http\Controllers\Data\AccountController::class, 'getSubscriptionPlans']);
});

// Dealer Application Management Routes (Admin/Staff)
Route::prefix('dealer-applications')->middleware('auth:sanctum')->group(function () {
    // Routes by application ID
    Route::post('/{applicationId}/approve', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'approve']);
    Route::post('/{applicationId}/reject', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'reject']);
    Route::post('/{applicationId}/process', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'processApplication']);
    
    // Routes by Odoo ID (for Odoo integration)
    Route::post('/odoo/{odooId}/approve', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'approveByOdooId']);
    Route::post('/odoo/{odooId}/reject', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'rejectByOdooId']);
    Route::post('/odoo/{odooId}/process', [App\Http\Controllers\Api\Dealer\DealerVerificationController::class, 'processByOdooId']);
});

// Listing Approval Routes (Operator/Admin)
Route::prefix('listings')->middleware('auth:sanctum')->group(function () {
    // Routes by listing ID
    Route::post('/{listingId}/approve', [App\Http\Controllers\Api\Listing\ListingApprovalController::class, 'approve']);
    Route::post('/{listingId}/reject', [App\Http\Controllers\Api\Listing\ListingApprovalController::class, 'reject']);
    Route::post('/{listingId}/process', [App\Http\Controllers\Api\Listing\ListingApprovalController::class, 'process']);
    
    // Routes by Odoo ID (for Odoo integration)
    Route::post('/odoo/{odooId}/approve', [App\Http\Controllers\Api\Listing\ListingApprovalController::class, 'approveByOdooId']);
    Route::post('/odoo/{odooId}/reject', [App\Http\Controllers\Api\Listing\ListingApprovalController::class, 'rejectByOdooId']);
    Route::post('/odoo/{odooId}/process', [App\Http\Controllers\Api\Listing\ListingApprovalController::class, 'processByOdooId']);
});

// Search Routes
Route::prefix('search')->group(function () {
    // Public search routes
    Route::get('/brands-models', [App\Http\Controllers\SearchController::class, 'searchBrandsAndModels']);
    Route::get('/brand-suggestions', [App\Http\Controllers\SearchController::class, 'getBrandSuggestions']);
    Route::get('/listings', [App\Http\Controllers\SearchController::class, 'searchListings']);
    Route::get('/sponsored-listings', [App\Http\Controllers\SearchController::class, 'getSponsoredListings']);
    Route::get('/brands', [App\Http\Controllers\SearchController::class, 'getAllBrands']);
    Route::get('/filter-options', [App\Http\Controllers\SearchController::class, 'getFilterOptions']);
    
    // Authenticated search routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/recent-searches', [App\Http\Controllers\SearchController::class, 'getRecentSearches']);
        Route::post('/save-search', [App\Http\Controllers\SearchController::class, 'saveSearchHistory']);
    });
    
    // Admin routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/clear-cache', [App\Http\Controllers\SearchController::class, 'clearSearchCache']);
    });
});

// Similar Cars Routes
Route::prefix('similar-cars')->group(function () {
    // Public similar cars routes
    Route::get('/car/{carListingId}', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCars']);
    Route::get('/brand-model', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCarsByBrandModel']);
    Route::get('/price', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCarsByPrice']);
    Route::get('/year', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCarsByYear']);
    Route::get('/body-type/{bodyTypeId}', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCarsByBodyType']);
    Route::get('/advanced', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCarsAdvanced']);
    Route::get('/recommendations', [App\Http\Controllers\SimilarCarsController::class, 'getSimilarCarsRecommendations']);
    
    // Admin routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/clear-cache', [App\Http\Controllers\SimilarCarsController::class, 'clearSimilarCarsCache']);
    });
});