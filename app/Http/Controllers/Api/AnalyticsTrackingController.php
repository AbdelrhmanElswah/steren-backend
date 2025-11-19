<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarListing;
use App\Models\CarListingAnalytics;
use App\Models\UserAnalytics;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AnalyticsTrackingController extends Controller
{
    use ApiResponse;

    /**
     * Track a view for a car listing
     */
    public function trackView(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'views');
    }

    /**
     * Track a share for a car listing
     */
    public function trackShare(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'shares');
    }

    /**
     * Track a call tap for a car listing
     */
    public function trackCallTap(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'call_taps');
    }

    /**
     * Track a WhatsApp click for a car listing
     */
    public function trackWhatsAppClick(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'whatsapp_clicks');
    }

    /**
     * Track an SMS click for a car listing
     */
    public function trackSmsClick(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'sms');
    }

    /**
     * Track an email click for a car listing
     */
    public function trackEmailClick(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'email');
    }

    /**
     * Track a watchlist save for a car listing
     */
    public function trackWatchlistSave(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'watchlist_saves');
    }

    /**
     * Track a favorite for a car listing
     */
    public function trackFavorite(Request $request, $carListingId): JsonResponse
    {
        return $this->trackMetric($request, $carListingId, 'favorites');
    }

    /**
     * Generic method to track any metric
     */
    private function trackMetric(Request $request, $carListingId, string $metricType): JsonResponse
    {
        try {
            // Validate car listing exists
            $carListing = CarListing::findOrFail($carListingId);

            // Prepare additional data
            $additionalData = [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ];

            // Add authenticated user info if available
            if (auth()->check()) {
                $additionalData['user_id'] = auth()->id();
            }

            // Track the metric for the car listing
            CarListingAnalytics::incrementMetric($carListingId, $metricType, $additionalData);

            // Track in user analytics for the listing owner (not the viewer)
            // This ensures the listing owner sees analytics for their listings
            if ($carListing->user_id) {
                UserAnalytics::incrementMetric($carListing->user_id, $metricType, $additionalData);
            }

            // Get updated analytics summary
            $analytics = CarListingAnalytics::getAnalyticsSummary($carListingId);

            return $this->successResponse([
                'metric_type' => $metricType,
                'car_listing_id' => $carListingId,
                'timestamp' => now()->toISOString(),
                'analytics' => $analytics,
                'total_views' => $analytics['views'] ?? 0, // Include total views for convenience
            ], ucfirst(str_replace('_', ' ', $metricType)) . ' tracked successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to track metric: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk track multiple metrics at once
     */
    public function trackBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'car_listing_id' => 'required|exists:car_listings,id',
            'metrics' => 'required|array',
            'metrics.*.type' => 'required|string|in:views,shares,call_taps,whatsapp_clicks,sms,email,watchlist_saves,favorites',
            'metrics.*.count' => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $carListingId = $request->car_listing_id;
            $carListing = CarListing::findOrFail($carListingId);
            $metrics = $request->metrics;

            $results = [];

            foreach ($metrics as $metric) {
                $metricType = $metric['type'];
                $count = $metric['count'];

                // Prepare additional data for bulk tracking
                $additionalData = [
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'timestamp' => now()->toISOString(),
                    'bulk_tracking' => true,
                ];

                if (auth()->check()) {
                    $additionalData['user_id'] = auth()->id();
                }

                // Increment metric count times
                for ($i = 0; $i < $count; $i++) {
                    CarListingAnalytics::incrementMetric($carListingId, $metricType, $additionalData);
                    
                    // Track in user analytics for the listing owner (not the viewer)
                    if ($carListing->user_id) {
                        UserAnalytics::incrementMetric($carListing->user_id, $metricType, $additionalData);
                    }
                }

                $results[] = [
                    'type' => $metricType,
                    'count' => $count,
                ];
            }

            // Get updated analytics summary
            $analytics = CarListingAnalytics::getAnalyticsSummary($carListingId);

            return $this->successResponse([
                'car_listing_id' => $carListingId,
                'tracked_metrics' => $results,
                'timestamp' => now()->toISOString(),
                'analytics' => $analytics,
                'total_views' => $analytics['views'] ?? 0,
            ], 'Bulk metrics tracked successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to track bulk metrics: ' . $e->getMessage(), 500);
        }
    }
}
