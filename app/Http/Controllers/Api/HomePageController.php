<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetHomePageDataRequest;
use App\Models\Notification;
use App\Services\SubscriptionBasedListingService;
use App\Services\CarListingFormatterService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class HomePageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SubscriptionBasedListingService $listingService
    ) {}

    /**
     * Get all home page data in a single request
     */
    public function getHomePageData(GetHomePageDataRequest $request): JsonResponse
    {
        try {
            $cityId = $request->input('city_id');
            $topPicksLimit = (int) $request->input('top_picks_limit', 10);
            // Manually check for Sanctum token authentication since route doesn't have auth middleware
            $user = null;
            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                $token = PersonalAccessToken::findToken($bearerToken);
                if ($token) {
                    $user = $token->tokenable;
                }
            }

            // Build cache key - include user ID if authenticated and city_id
            $userId = $user?->id ?? 'guest';
            $cacheKey = 'home_page_data_' . $userId . '_' . ($cityId ?? 'all') . '_' . $topPicksLimit;

            // Cache for 1 hour (3600 seconds) - shorter cache for personalized data
            // Note: unread_count and top_picks are excluded from cache and fetched fresh every time
            // top_picks depends on user favorites, so it needs to be personalized per request
            $data = Cache::remember($cacheKey, 3600, function () use ($user, $cityId, $topPicksLimit) {
                return [
                    'brands' => $this->getBrands(),
                    'authorized_agencies' => $this->getAuthorizedAgencies(),
                    'body_types' => $this->getBodyTypes(),
                    'cities' => $this->getCities(),
                    'budgets' => $this->getBudgets(),
                    'carousels' => $this->getCarousels(),
                    'studios' => $this->getStudios($cityId),
                ];
            });

            // Fetch unread_count and top_picks fresh every time (not cached)
            // These depend on the authenticated user's data
            $data['unread_count'] = $this->getUnreadCount($user);
            $data['top_picks'] = $this->getTopPicks($user, $cityId, $topPicksLimit);

            return $this->successResponse($data, 'Home page data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve home page data: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get unread notification count
     */
    private function getUnreadCount($user): array
    {
        if (!$user) {
            return ['unread_count' => 0];
        }

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return ['unread_count' => $unreadCount];
    }

    /**
     * Get brands data (same structure as /api/odoo/brands)
     */
    private function getBrands(): array
    {
        $cacheKey = 'home_brands';
        
        return Cache::remember($cacheKey, 86400, function () {
            $brands = \App\Models\CarBrand::select('id', 'odoo_id', 'name')
                ->with(['media:id,modelable_type,modelable_id,media,path_file_name,file_type,info,order'])
                ->orderBy('name')
                ->get();

            $brandsCollection = $brands->map(function ($brand) {
                $mediaLink = null;
                if ($brand->media && $brand->media->public_url) {
                    $mediaLink = $brand->media->public_url;
                } elseif ($brand->media && $brand->media->path_file_name) {
                    $mediaLink = $brand->media->getS3PublicUrl($brand->media->path_file_name);
                }

                return [
                    'id' => $brand->id,
                    'odoo_id' => $brand->odoo_id,
                    'name' => $brand->name,
                    'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                ];
            })->toArray();

            return [
                'data' => $brandsCollection,
                'total' => count($brandsCollection),
            ];
        });
    }

    /**
     * Get authorized agencies data (same structure as /api/odoo/authorized-agencies)
     */
    private function getAuthorizedAgencies(): array
    {
        $cacheKey = 'home_authorized_agencies';
        
        return Cache::remember($cacheKey, 86400, function () {
            // Get partner IDs to pre-load users and studios
            $partnerIds = \App\Models\AuthorizedAgency::pluck('partner_id')->filter()->unique();
            
            // Pre-load users and studios
            $studioMap = [];
            if ($partnerIds->isNotEmpty()) {
                $users = \App\Models\User::whereIn('partner_id', $partnerIds)
                    ->select('id', 'partner_id')
                    ->get()
                    ->keyBy('partner_id');
                
                $userIds = $users->pluck('id');
                
                if ($userIds->isNotEmpty()) {
                    $studios = \App\Models\DealerStudio::whereIn('dealer_id', $userIds)
                        ->select('id', 'dealer_id')
                        ->get()
                        ->groupBy('dealer_id');
                    
                    foreach ($users as $partnerId => $user) {
                        if (isset($studios[$user->id]) && $studios[$user->id]->isNotEmpty()) {
                            $studioMap[$partnerId] = $studios[$user->id]->first()->id;
                        }
                    }
                }
            }

            $agencies = \App\Models\AuthorizedAgency::select('id', 'name', 'partner_id')
                ->with(['media:id,modelable_type,modelable_id,media,path_file_name,file_type,info,order'])
                ->orderBy('name')
                ->get();
            
            $agenciesCollection = $agencies->map(function ($agency) use ($studioMap) {
                $mediaLink = null;
                if ($agency->media && $agency->media->path_file_name) {
                    $mediaLink = $agency->media->getS3PublicUrl($agency->media->path_file_name);
                }

                return [
                    'id' => $agency->id,
                    'name' => $agency->name,
                    'partner_id' => $agency->partner_id,
                    'studio_id' => $studioMap[$agency->partner_id] ?? null,
                    'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                ];
            })->toArray();

            return [
                'data' => $agenciesCollection,
                'total' => count($agenciesCollection),
            ];
        });
    }

    /**
     * Get body types data (same structure as /api/odoo/body-types)
     */
    private function getBodyTypes(): array
    {
        $cacheKey = 'home_body_types';
        
        return Cache::remember($cacheKey, 86400, function () {
            $bodyTypes = \App\Models\BodyType::select('id', 'odoo_id', 'name')
                ->with(['medias:id,modelable_type,modelable_id,media,path_file_name,file_type,info,order'])
                ->orderBy('name')
                ->get();

            $bodyTypesCollection = $bodyTypes->map(function ($bodyType) {
                $mediaLink = null;
                if ($bodyType->medias->isNotEmpty()) {
                    $firstMedia = $bodyType->medias->first();
                    if ($firstMedia && $firstMedia->path_file_name) {
                        $mediaLink = $firstMedia->getS3PublicUrl($firstMedia->path_file_name);
                    }
                }

                return [
                    'id' => $bodyType->id,
                    'odoo_id' => $bodyType->odoo_id,
                    'name' => $bodyType->name,
                    'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                ];
            })->toArray();

            return [
                'data' => $bodyTypesCollection,
                'total' => count($bodyTypesCollection),
            ];
        });
    }

    /**
     * Get cities data (same structure as /api/odoo/cities)
     */
    private function getCities(): array
    {
        $cacheKey = 'home_cities';
        
        return Cache::remember($cacheKey, 86400, function () {
            $cities = \App\Models\City::orderBy('name')->get();

            $citiesCollection = $cities->map(function ($city) {
                return [
                    'id' => $city->id,
                    'odoo_id' => $city->odoo_id,
                    'name' => $city->name,
                    'created_at' => $city->created_at?->toISOString(),
                    'updated_at' => $city->updated_at?->toISOString(),
                    'deleted_at' => $city->deleted_at?->toISOString(),
                ];
            })->toArray();

            return [
                'data' => $citiesCollection,
                'total' => count($citiesCollection),
            ];
        });
    }

    /**
     * Get budgets data (same structure as /api/odoo/budgets)
     */
    private function getBudgets(): array
    {
        $cacheKey = 'home_budgets';
        
        return Cache::remember($cacheKey, 86400, function () {
            $budgets = \App\Models\Budget::where('is_active', true)
                ->orderBy('sequence')
                ->orderBy('value')
                ->get();

            $budgetsCollection = $budgets->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'odoo_id' => $budget->odoo_id,
                    'name' => $budget->name,
                    'value' => $budget->value,
                    'formatted_value' => $budget->formatted_value,
                    'sequence' => $budget->sequence,
                    'is_active' => $budget->is_active,
                    'created_at' => $budget->created_at?->toISOString(),
                    'updated_at' => $budget->updated_at?->toISOString(),
                ];
            })->toArray();

            return $budgetsCollection; // Returns array directly, matching BudgetController structure
        });
    }

    /**
     * Get carousels data (same structure as /api/carousels/all)
     */
    private function getCarousels(): array
    {
        $cacheKey = 'home_carousels_all';
        
        return Cache::remember($cacheKey, 86400, function () {
            $carousels = \App\Models\Carousel::select('id', 'odoo_id', 'title', 'description', 'button_text', 'link_url', 'sort_order')
                ->with(['medias' => function($q) {
                    $q->select('id', 'modelable_type', 'modelable_id', 'media', 'path_file_name', 'file_type', 'info', 'order')
                        ->where('info', 'main')
                        ->limit(1);
                }])
                ->where('is_active', true)
                ->ordered()
                ->get();

            $carouselsCollection = $carousels->map(function ($carousel) {
                $mediaLink = null;
                if ($carousel->medias->isNotEmpty()) {
                    $firstMedia = $carousel->medias->first();
                    if ($firstMedia && $firstMedia->path_file_name) {
                        $mediaLink = $firstMedia->getS3PublicUrl($firstMedia->path_file_name);
                    }
                }

                return [
                    'id' => $carousel->odoo_id,
                    'title' => $carousel->title,
                    'description' => $carousel->description,
                    'button_text' => $carousel->button_text,
                    'link_url' => $carousel->link_url,
                    'sort_order' => $carousel->sort_order,
                    'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                ];
            })->toArray();

            return [
                'data' => $carouselsCollection,
                'total' => count($carouselsCollection),
            ];
        });
    }

    /**
     * Get top picks data (same structure as /api/subscription-listings/top-picks)
     */
    private function getTopPicks($user, ?int $cityId, int $limit): array
    {
        $favBrandIds = $user?->fav_brand_ids ?? [];
        $favBodyTypeIds = $user?->fav_body_type_ids ?? [];
        
        $filters = [];
        if ($cityId) {
            $filters['city_id'] = $cityId;
        }
        
        $formatter = app(CarListingFormatterService::class);
        $formattedListings = [];
        
        // Priority logic: brands+body_types -> brands -> city -> no filters
        // Step 1: Try brands AND body_types (both required, city optional)
        if (!empty($favBrandIds) && !empty($favBodyTypeIds)) {
            $testFilters = array_merge($filters, [
                'brand_ids' => $favBrandIds,
                'body_type_ids' => $favBodyTypeIds,
            ]);
            $topPicks = $this->listingService->getGeneralTopPicks($limit, $testFilters);
            $formattedListings = $formatter->formatListings($topPicks);
        }
        
        // Step 2: Try brands only (if no results from step 1)
        if (empty($formattedListings) && !empty($favBrandIds)) {
            $testFilters = array_merge($filters, [
                'brand_ids' => $favBrandIds,
            ]);
            $topPicks = $this->listingService->getGeneralTopPicks($limit, $testFilters);
            $formattedListings = $formatter->formatListings($topPicks);
        }
        
        // Step 3: Try city only (if no results from step 2)
        if (empty($formattedListings) && $cityId) {
            $topPicks = $this->listingService->getGeneralTopPicks($limit, $filters);
            $formattedListings = $formatter->formatListings($topPicks);
        }
        
        // Step 4: Fallback to no filters (if no results from step 3)
        if (empty($formattedListings)) {
            $topPicks = $this->listingService->getGeneralTopPicks($limit, []);
            $formattedListings = $formatter->formatListings($topPicks);
        }

        return [
            'top_picks' => $formattedListings,
            'count' => count($formattedListings),
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get studios data (same structure as /api/studios)
     */
    private function getStudios(?int $cityId): array
    {
        $filters = array_filter([
            'city_id' => $cityId,
        ], fn ($value) => $value !== null && $value !== '');
        
        $studios = $this->listingService->getAllStudios($filters);

        // Format studios with their listings
        $formattedStudios = $studios->filter(function ($studio) {
            return $studio->carListings && $studio->carListings->count() > 0;
        })->map(function ($studio) {
            // Format car listings with only required fields
            $formattedListings = $studio->carListings->map(function ($listing) {
                $media = $listing->relationLoaded('media') ? $listing->media : null;
                $primaryMedia = $media && $media instanceof \Illuminate\Database\Eloquent\Collection 
                    ? $media->sortBy('order')->first() 
                    : null;
                
                $mediaLink = null;
                if ($primaryMedia && $primaryMedia->path_file_name) {
                    $mediaLink = $primaryMedia->getS3PublicUrl($primaryMedia->path_file_name);
                }
                
                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'price' => $listing->price,
                    'price_currency' => $listing->price_currency,
                    'formatted_price' => $listing->formatted_price,
                    'mileage' => $listing->mileage,
                    'mileage_type' => $listing->mileageType?->id,
                    'mileage_type_name' => $listing->mileageType?->name,
                    'formatted_mileage' => $listing->formatted_mileage,
                    'views' => $listing->views,
                    'formatted_views' => $listing->formatted_views,
                    'gcc_import' => $listing->gcc_import,
                    'media' => $mediaLink,
                ];
            });
            
            return [
                'id' => $studio->id,
                'name' => $studio->name,
                'city_id' => $studio->city_id,
                'is_verified' => $studio->is_verified,
                'dealer' => $studio->dealer ? [
                    'id' => $studio->dealer->id,
                    'name' => $studio->dealer->full_name,
                    'email' => $studio->dealer->email,
                ] : null,
                'city' => $studio->city ? [
                    'id' => $studio->city->id,
                    'name' => $studio->city->name,
                ] : null,
                'car_listings' => $formattedListings,
                'listings_count' => $studio->carListings->count(),
            ];
        })->values()->toArray();

        return [
            'studios' => $formattedStudios,
            'count' => count($formattedStudios),
        ];
    }
}

