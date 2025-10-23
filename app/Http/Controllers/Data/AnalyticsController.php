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
                        'image' => $listing->media->first()?->media_link,
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

            // Calculate summary with trends
            $summary = [
                'views' => [
                    'value' => CarListingAnalytics::getTotalViews($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'views', $period)
                ],
                'days_active' => CarListingAnalytics::getDaysActive($carListingId),
                'whatsapp_clicks' => [
                    'value' => CarListingAnalytics::getTotalWhatsAppClicks($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'whatsapp_clicks', $period)
                ],
                'shares' => [
                    'value' => CarListingAnalytics::getTotalShares($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'shares', $period)
                ],
                'watchlist_saves' => [
                    'value' => CarListingAnalytics::getTotalWatchlistSaves($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'watchlist_saves', $period)
                ],
                'call_taps' => [
                    'value' => CarListingAnalytics::getTotalCallTaps($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'call_taps', $period)
                ],
                'sms' => [
                    'value' => CarListingAnalytics::getTotalSmsClicks($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'sms', $period)
                ],
                'email' => [
                    'value' => CarListingAnalytics::getTotalEmailClicks($carListingId),
                    'trend' => CarListingAnalytics::getMetricTrend($carListingId, 'email', $period)
                ],
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
     * Get user's car listings (My Ads) with analytics
     */
    public function getMyAds(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $filter = $request->get('filter', 'all'); // all, posts, boosted, sold
            $sort = $request->get('sort', 'latest'); // latest, oldest, views, price
            
            $query = $user->carListings()
                ->with([
                    'brand:id,name,odoo_id',
                    'carModel:id,name,car_brand_id', 
                    'bodyType:id,name',
                    'location:id,name',
                    'media:id,modelable_type,modelable_id,media,path_file_name,file_type,info,order',
                    'carCondition:id,name',
                    'fuelType:id,name',
                    'transmission:id,name',
                    'exteriorColor:id,name',
                    'driveType:id,name',
                    'cylinder:id,name',
                    'carTrim:id,name,car_model_id',
                    'features:id,name,category,description,icon',
                    'specifications:id,name,value,unit,specification_type,description,is_highlighted',
                    'badges:id,name',
                    'status', 
                    'listingPromotion'
                ])
                ->withCount('favorites');
            
            // Apply filters
            switch ($filter) {
                case 'posts':
                    $query->where('is_promoted', false);
                    break;
                case 'boosted':
                    $query->where('is_promoted', true);
                    break;
                case 'sold':
                    $query->where('status', 'sold');
                    break;
                case 'all':
                default:
                    // No additional filter
                    break;
            }
            
            // Apply sorting
            switch ($sort) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'views':
                    $query->orderBy('views', 'desc');
                    break;
                case 'price':
                    $query->orderBy('price', 'desc');
                    break;
                case 'latest':
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }
            
            $listings = $query->get()->map(function ($listing) {
                // Add detailed specifications
                $detailedSpecifications = [
                    'engine_size' => $listing->engine_size,
                    'engine_type' => $listing->engine_type,
                    'horsepower' => $listing->horsepower,
                    'torque' => $listing->torque,
                    'transmission' => $listing->transmission?->name,
                    'cylinders' => $listing->cylinder?->name,
                    'drive_type' => $listing->driveType?->name,
                    'acceleration_0_100' => $listing->acceleration_0_100,
                    'top_speed' => $listing->top_speed,
                    'seating_capacity' => $listing->seating_capacity,
                    'mileage_efficiency' => $listing->formatted_mileage_efficiency,
                    'dimensions' => $listing->formatted_dimensions,
                ];

                // Group features by category
                $featuresByCategory = collect();
                if ($listing->relationLoaded('features') && $listing->features instanceof \Illuminate\Database\Eloquent\Collection && $listing->features->isNotEmpty()) {
                    $featuresByCategory = $listing->features->groupBy('category')->map(function ($features) {
                        return $features->map(function ($feature) {
                            return [
                                'id' => $feature->id,
                                'name' => $feature->name,
                                'description' => $feature->description,
                                'icon' => $feature->icon,
                                'is_included' => $feature->pivot->is_included ?? false,
                                'notes' => $feature->pivot->notes ?? null,
                            ];
                        });
                    });
                }

                // Group specifications by type
                $specificationsByType = collect();
                if ($listing->relationLoaded('specifications') && $listing->specifications instanceof \Illuminate\Database\Eloquent\Collection && $listing->specifications->isNotEmpty()) {
                    $specificationsByType = $listing->specifications->groupBy('specification_type')->map(function ($specs) {
                        return $specs->map(function ($spec) {
                            return [
                                'id' => $spec->id,
                                'name' => $spec->name,
                                'value' => $spec->value,
                                'unit' => $spec->unit,
                                'formatted_value' => $spec->formatted_value,
                                'description' => $spec->description,
                                'is_highlighted' => $spec->is_highlighted,
                            ];
                        });
                    });
                }

                return [
                    'id' => $listing->id,
                    'title' => $listing->full_title,
                    'price' => $listing->formatted_price,
                    'status' => $listing->status,
                    'status_label' => $listing->getStatusLabelAttribute(),
                    'is_promoted' => $listing->is_promoted,
                    'promotion_type' => $listing->is_promoted ? $listing->getPromotionTypeAttribute() : null,
                    'image' => $listing->media->first()?->media_link,
                    'views' => $listing->total_views,
                    'days_active' => $listing->days_active,
                    'badges' => $listing->active_badges,
                    'seller_name' => $listing->seller_name,
                    'created_at' => $listing->created_at->toISOString(),
                    'updated_at' => $listing->updated_at->toISOString(),
                    // Add comprehensive data like car-listings endpoint
                    'brand' => $listing->brand,
                    'carModel' => $listing->carModel,
                    'bodyType' => $listing->bodyType,
                    'location' => $listing->location,
                    'carCondition' => $listing->carCondition,
                    'fuelType' => $listing->fuelType,
                    'transmission' => $listing->transmission,
                    'exteriorColor' => $listing->exteriorColor,
                    'driveType' => $listing->driveType,
                    'cylinder' => $listing->cylinder,
                    'carTrim' => $listing->carTrim,
                    'detailed_specifications' => $detailedSpecifications,
                    'features_by_category' => $featuresByCategory,
                    'specifications_by_type' => $specificationsByType,
                    'status_badge' => $listing->getStatusBadgeAttribute(),
                    'active_badges' => $listing->getActiveBadgesAttribute(),
                ];
            });
            
            // Get summary counts
            $summary = [
                'total_posts' => $user->carListings()->where('is_promoted', false)->count(),
                'total_boosted' => $user->carListings()->where('is_promoted', true)->count(),
                'total_sold' => $user->carListings()->where('status', 'sold')->count(),
                'total_active' => $user->carListings()->where('status', 'active')->count(),
            ];
            
            $data = [
                'listings' => $listings,
                'summary' => $summary,
                'filter' => $filter,
                'sort' => $sort,
            ];
            
            return $this->successResponse($data, 'My ads retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve my ads: ' . $e->getMessage(), 500);
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
