<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\UserAnalytics;
use App\Models\CarListingAnalytics;
use App\Models\CarListing;
use App\Models\AdBoost;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * Get user analytics with time filtering
     */
    public function getUserAnalytics(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $period = $request->get('period', 'this_month'); // last_7_days, this_month, all_time
            
            $startDate = $this->getStartDate($period);
            $endDate = now()->toDateString();
            
            // Get analytics data
            $analytics = UserAnalytics::where('user_id', $user->id)
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('date', '>=', $startDate);
                })
                ->get()
                ->groupBy('metric_type');

            // Calculate totals
            $summary = [
                'total_listed_cars' => $user->total_listings,
                'total_active_cars' => $user->carListings()->where('status', 'active')->count(),
                'total_views' => $analytics->get('views', collect())->sum('metric_value'),
                'total_shares' => $analytics->get('shares', collect())->sum('metric_value'),
                'total_call_taps' => $analytics->get('call_taps', collect())->sum('metric_value'),
                'total_whatsapp_clicks' => $analytics->get('whatsapp_clicks', collect())->sum('metric_value'),
                'total_sms' => $analytics->get('sms', collect())->sum('metric_value'),
                'total_email' => $analytics->get('email', collect())->sum('metric_value'),
                'total_watchlist_saved' => $analytics->get('watchlist_saved', collect())->sum('metric_value'),
            ];

            // Get recently added cars
            $recentlyAdded = $user->carListings()
                ->with(['brand', 'carModel', 'media'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($listing) {
                    return [
                        'id' => $listing->id,
                        'title' => $listing->full_title,
                        'price' => $listing->formatted_price,
                        'image' => $listing->media->first()?->url,
                        'status' => $listing->status,
                        'views' => CarListingAnalytics::getTotalViews($listing->id),
                        'badges' => $listing->active_badges,
                    ];
                });

            $data = [
                'summary' => $summary,
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'recently_added' => $recentlyAdded,
                'daily_data' => $analytics,
            ];

            return $this->successResponse($data, 'User analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get car listing analytics
     */
    public function getCarListingAnalytics(Request $request, $carListingId): JsonResponse
    {
        try {
            $user = auth()->user();
            $period = $request->get('period', 'this_month');
            
            // Verify user owns this car listing
            $carListing = $user->carListings()->findOrFail($carListingId);
            
            $startDate = $this->getStartDate($period);
            $endDate = now()->toDateString();
            
            // Get analytics data
            $analytics = CarListingAnalytics::where('car_listing_id', $carListingId)
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('date', '>=', $startDate);
                })
                ->get()
                ->groupBy('metric_type');

            // Calculate summary
            $summary = [
                'views' => CarListingAnalytics::getTotalViews($carListingId),
                'days_active' => CarListingAnalytics::getDaysActive($carListingId),
                'whatsapp_clicks' => CarListingAnalytics::getTotalWhatsAppClicks($carListingId),
                'shares' => CarListingAnalytics::getTotalShares($carListingId),
                'watchlist_saves' => CarListingAnalytics::getTotalWatchlistSaves($carListingId),
                'call_taps' => CarListingAnalytics::getTotalCallTaps($carListingId),
                'sms' => CarListingAnalytics::getTotalSmsClicks($carListingId),
                'email' => CarListingAnalytics::getTotalEmailClicks($carListingId),
            ];

            // Get active boosts
            $activeBoosts = $carListing->activeBoosts()
                ->with('user')
                ->get()
                ->map(function ($boost) {
                    return [
                        'id' => $boost->id,
                        'type' => $boost->boost_type,
                        'type_label' => $boost->boost_type_label,
                        'cost' => $boost->formatted_cost,
                        'duration_days' => $boost->duration_days,
                        'days_remaining' => $boost->days_remaining,
                        'expires_at' => $boost->formatted_expiry_date,
                        'features' => $boost->boost_features,
                    ];
                });

            $data = [
                'car_listing' => [
                    'id' => $carListing->id,
                    'title' => $carListing->full_title,
                    'price' => $carListing->formatted_price,
                    'status' => $carListing->status,
                    'image' => $carListing->media->first()?->url,
                    'badges' => $carListing->active_badges,
                ],
                'summary' => $summary,
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'active_boosts' => $activeBoosts,
                'daily_data' => $analytics,
            ];

            return $this->successResponse($data, 'Car listing analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car listing analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available boost options
     */
    public function getBoostOptions(): JsonResponse
    {
        try {
            $boostOptions = [
                [
                    'id' => 'sponsored',
                    'name' => 'Sponsored Listing',
                    'description' => 'Get your listing featured at the top of search results',
                    'cost' => 50000,
                    'currency' => 'IQD',
                    'duration_days' => 7,
                    'features' => [
                        'Featured at top of search results',
                        'Increased visibility',
                        'Priority placement',
                        'Sponsored badge',
                    ],
                ],
                [
                    'id' => 'featured',
                    'name' => 'Featured Listing',
                    'description' => 'Highlight your listing with premium placement',
                    'cost' => 75000,
                    'currency' => 'IQD',
                    'duration_days' => 14,
                    'features' => [
                        'Premium placement in search',
                        'Featured badge',
                        'Enhanced visibility',
                        'Priority in recommendations',
                    ],
                ],
                [
                    'id' => 'premium',
                    'name' => 'Premium Boost',
                    'description' => 'Maximum exposure with all premium features',
                    'cost' => 100000,
                    'currency' => 'IQD',
                    'duration_days' => 30,
                    'features' => [
                        'Maximum visibility',
                        'Premium badge',
                        'Featured placement',
                        'Priority in all listings',
                        'Enhanced analytics',
                    ],
                ],
            ];

            return $this->successResponse($boostOptions, 'Boost options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve boost options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get time period start date
     */
    private function getStartDate($period)
    {
        return match ($period) {
            'last_7_days' => now()->subDays(7)->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'all_time' => null,
            default => now()->startOfMonth()->toDateString(),
        };
    }
}
