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

// Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\Auth\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login']);
    Route::post('/send-otp', [App\Http\Controllers\Auth\AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [App\Http\Controllers\Auth\AuthController::class, 'verifyOtp']);
    Route::post('/password/forgot', [App\Http\Controllers\Auth\AuthController::class, 'startPasswordReset']);
    Route::post('/password/reset', [App\Http\Controllers\Auth\AuthController::class, 'completePasswordReset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [App\Http\Controllers\Auth\AuthController::class, 'me']);
        Route::post('/logout', [App\Http\Controllers\Auth\AuthController::class, 'logout']);
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
    Route::get('/subscription-plans', [App\Http\Controllers\Data\AccountController::class, 'getSubscriptionPlans']);
    Route::get('/my-subscription', [App\Http\Controllers\Data\AccountController::class, 'getMySubscription']);
    Route::get('/language-options', [App\Http\Controllers\Data\AccountController::class, 'getLanguageOptions']);
    Route::get('/theme-options', [App\Http\Controllers\Data\AccountController::class, 'getThemeOptions']);
    Route::get('/social-auth-options', [App\Http\Controllers\Data\AccountController::class, 'getSocialAuthOptions']);
});

// Analytics Routes - GET only
Route::prefix('analytics')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', [App\Http\Controllers\Data\AnalyticsController::class, 'getUserAnalytics']);
    Route::get('/car-listing/{carListingId}', [App\Http\Controllers\Data\AnalyticsController::class, 'getCarListingAnalytics']);
    Route::get('/boost-options', [App\Http\Controllers\Data\AnalyticsController::class, 'getBoostOptions']);
});

// Payment Processing Routes - GET only
Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::get('/status/{paymentId}', [App\Http\Controllers\Data\PaymentController::class, 'getPaymentStatus']);
});
