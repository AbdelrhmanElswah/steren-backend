<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\UserAnalytics;
use App\Models\CarListingAnalytics;
use App\Models\CarListing;
use App\Models\AdBoost;
use App\Services\CarListingFormatterService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    use ApiResponse;

    protected $formatter;

    public function __construct(CarListingFormatterService $formatter)
    {
        $this->formatter = $formatter;
    }

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
            
            // Get user's car listing IDs
            $userListingIds = $user->carListings()->pluck('id');
            
            // Aggregate analytics from car_listing_analytics for user's listings
            // This uses the existing data in car_listing_analytics table
            $analyticsQuery = \App\Models\CarListingAnalytics::whereIn('car_listing_id', $userListingIds)
                ->when($startDate, function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('date', [$startDate, $endDate]);
                });
            
            // Calculate totals by aggregating from car_listing_analytics
            $summary = [
                'total_listed_cars' => $user->carListings()->count(),
                'total_active_cars' => $user->carListings()->where('status', 'active')->count(),
                'total_views' => (clone $analyticsQuery)->where('metric_type', 'views')->sum('metric_value') ?? 0,
                'total_shares' => (clone $analyticsQuery)->where('metric_type', 'shares')->sum('metric_value') ?? 0,
                'total_call_taps' => (clone $analyticsQuery)->where('metric_type', 'call_taps')->sum('metric_value') ?? 0,
                'total_whatsapp_clicks' => (clone $analyticsQuery)->where('metric_type', 'whatsapp_clicks')->sum('metric_value') ?? 0,
                'total_sms' => (clone $analyticsQuery)->where('metric_type', 'sms')->sum('metric_value') ?? 0,
                'total_email' => (clone $analyticsQuery)->where('metric_type', 'email')->sum('metric_value') ?? 0,
                'total_watchlist_saved' => (clone $analyticsQuery)->where('metric_type', 'watchlist_saves')->sum('metric_value') ?? 0,
            ];
            
            // Get analytics data grouped by metric_type for daily_data
            $analytics = (clone $analyticsQuery)->get()->groupBy('metric_type');

            // Get recently added cars
            $recentlyAdded = $user->carListings()
                ->with([
                    'brand', 
                    'carModel', 
                    'media' => function($mediaQuery) {
                        $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                            ->orderBy('order');
                    }
                ])
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
            
            // Select only essential fields for listing card UI (matching /api/car-listings)
            $query = $user->carListings()
                ->select([
                    'id', 'odoo_id', 'title', 'price', 'price_currency', 'year', 'trim', 'doors',
                    'mileage', 'mileage_type_id', 'city_id', 'car_brand_id', 'car_body_type_id', 'car_model_id',
                    'car_condition_id', 'fuel_type_id', 'transmission_id', 'exterior_color_id',
                    'drive_type_id', 'cylinder_id', 'studio_id', 'is_sponsored', 'is_featured',
                    'status', 'status_id', 'operator_decision', 'is_new_arrival', 'description',
                    'has_warranty', 'is_inspected', 'is_verified', 'has_insurance',
                    'origin_country', 'gcc_import', 'plate_status', 'condition_type',
                    'featured_at', 'created_at', 'user_id', 'is_promoted',
                    'engine_size', 'engine_type', 'horsepower', 'torque', 'acceleration_0_100',
                    'top_speed', 'seating_capacity', 'mileage_efficiency', 'length', 'width', 'height'
                ])
                ->with([
                    'brand:id,name',
                    'carModel:id,name,car_brand_id', 
                    'bodyType:id,name',
                    'city:id,name',
                    'media' => function($mediaQuery) {
                        $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                            ->orderBy('order');
                    },
                    'inspection' => function($inspectionQuery) {
                        $inspectionQuery->select('id', 'car_listing_id', 'right_side', 'left_side', 'roof', 'front', 'back');
                    },
                    'carCondition:id,name',
                    'fuelType:id,name',
                    'transmission:id,name',
                    'exteriorColor:id,name',
                    'driveType:id,name',
                    'cylinder:id,name',
                    'mileageType:id,name',
                    'carTrim:id,name,car_model_id',
                    'features:id,name,category,description,icon',
                    'specifications:id,name,value,unit,specification_type,description,is_highlighted',
                    'badges:id,name',
                    'status', 
                    'listingPromotion',
                    'user:id,full_name',
                    'user.profilePictureMedia' => function($profileQuery) {
                        $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                            ->where('info', 'profile_picture');
                    }
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
            
            // Get media counts for all listings in one query (for UI gallery indicator)
            $listings = $query->get();
            $listingIds = $listings->pluck('id');
            $mediaCounts = \App\Models\Media::where('modelable_type', 'App\Models\CarListing')
                ->whereIn('modelable_id', $listingIds)
                ->selectRaw('modelable_id, COUNT(*) as count')
                ->groupBy('modelable_id')
                ->pluck('count', 'modelable_id');
            
            // Format listings using CarListingFormatterService to match /api/car-listings structure
            // Then add analytics-specific fields
            $formattedListings = $listings->map(function ($listing) use ($mediaCounts) {
                // Get base structure from formatter (matches /api/car-listings)
                $baseData = $this->formatter->formatListing($listing, $mediaCounts->get($listing->id, null));
                
                // Add detailed specifications (analytics-specific)
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

                // Group features by category (analytics-specific)
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

                // Group specifications by type (analytics-specific)
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

                // Merge base data with analytics-specific fields
                return array_merge($baseData, [
                    // Analytics-specific fields (keep these)
                    'status' => $listing->status,
                    'status_label' => $listing->getStatusLabelAttribute(),
                    'promotion_type' => $listing->is_promoted ? $listing->getPromotionTypeAttribute() : null,
                    'views' => (string) $listing->total_views,
                    'days_active' => $listing->days_active,
                    'badges' => $listing->active_badges,
                    'detailed_specifications' => $detailedSpecifications,
                    'features_by_category' => $featuresByCategory,
                    'specifications_by_type' => $specificationsByType,
                    'status_badge' => $listing->getStatusBadgeAttribute(),
                    'active_badges' => $listing->getActiveBadgesAttribute(),
                    // Add carTrim (analytics-specific, not in base formatter)
                    'car_trim' => $listing->carTrim ? [
                        'id' => $listing->carTrim->id,
                        'name' => $listing->carTrim->name,
                        'car_model_id' => $listing->carTrim->car_model_id,
                    ] : null,
                ]);
            });
            
            // Get summary counts
            $summary = [
                'total_posts' => $user->carListings()->where('is_promoted', false)->count(),
                'total_boosted' => $user->carListings()->where('is_promoted', true)->count(),
                'total_sold' => $user->carListings()->where('status', 'sold')->count(),
                'total_active' => $user->carListings()->where('status', 'active')->count(),
            ];
            
            $data = [
                'listings' => $formattedListings,
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
