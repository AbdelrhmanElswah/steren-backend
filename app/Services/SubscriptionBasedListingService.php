<?php

namespace App\Services;

use App\Models\User;
use App\Models\CarListing;
use App\Models\SubscriptionPlan;
use App\Models\DealerStudio;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

class SubscriptionBasedListingService
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Check if user is a dealer using is_dealer field
     */
    protected function isDealer(User $user): bool
    {
        // Check if user has is_dealer = 1 (true)
        return $user->isDealer();
    }

    /**
     * Get car listings based on user's subscription plan
     * COMMENTED OUT: No subscription plans currently
     */
    public function getCarListingsForUser(User $user, array $filters = [])
    {
        // COMMENTED OUT: Subscription plan system
        // $plan = $this->subscriptionService->getSubscriptionPlan($user);
        // 
        // if (!$plan) {
        //     return $this->getFreeTierListings($filters);
        // }
        //
        // return match ($plan->name) {
        //     'Start' => $this->getStartPlanListings($user, $filters),
        //     'Pro' => $this->getProPlanListings($user, $filters),
        //     'Dealer' => $this->getDealerPlanListings($user, $filters),
        //     default => $this->getFreeTierListings($filters),
        // };

        // SIMPLIFIED: Check if user is dealer, otherwise return free tier
        if ($this->isDealer($user)) {
            return $this->getDealerPlanListings($user, $filters);
        }

        return $this->getFreeTierListings($filters);
    }

    /**
     * Get top picks for user based on subscription plan
     * COMMENTED OUT: No subscription plans currently
     */
    public function getTopPicksForUser(User $user, int $limit = 10, array $filters = [])
    {
        // COMMENTED OUT: Subscription plan system
        // $plan = $this->subscriptionService->getSubscriptionPlan($user);
        // 
        // if (!$plan || !$plan->is_top_picks) {
        //     return collect();
        // }
        //
        // if ($plan->name === 'Dealer') {
        //     return $this->getDealerTopPicks($user, $limit);
        // }
        //
        // return $this->getGeneralTopPicks($limit);

        // SIMPLIFIED: Dealers get dealer top picks, others get general
        if ($this->isDealer($user)) {
            return $this->getDealerTopPicks($user, $limit, $filters);
        }

        return $this->getGeneralTopPicks($limit, $filters);
    }

    /**
     * Get "You May Also Like" recommendations for user
     * COMMENTED OUT: No subscription plans currently
     */
    public function getYouMayAlsoLikeForUser(User $user, CarListing $excludeListing, int $limit = 6)
    {
        // COMMENTED OUT: Subscription plan system
        // $plan = $this->subscriptionService->getSubscriptionPlan($user);
        // 
        // if (!$plan || !$plan->is_you_may_also_like) {
        //     return collect();
        // }
        //
        // if ($plan->name === 'Dealer') {
        //     return $this->getDealerRecommendations($user, $excludeListing, $limit);
        // }
        //
        // return $this->getGeneralRecommendations($excludeListing, $limit);

        // SIMPLIFIED: Dealers get dealer recommendations, others get general
        if ($this->isDealer($user)) {
            return $this->getDealerRecommendations($user, $excludeListing, $limit);
        }

        return $this->getGeneralRecommendations($excludeListing, $limit);
    }

    /**
     * Get car listings for dealer's studio
     */
    public function getCarListingsForDealerStudio(User $dealer, int $studioId, array $filters = [])
    {
        $studio = DealerStudio::where('dealer_id', $dealer->id)
            ->where('id', $studioId)
            ->where('is_active', true)
            ->first();

        if (!$studio) {
            return collect();
        }

        $query = $studio->carListings()
            ->with(['brand', 'carModel', 'bodyType', 'location', 'media'])
            ->where('status', 'active');

        // Apply filters
        if (isset($filters['brand_id'])) {
            $query->where('car_brand_id', $filters['brand_id']);
        }

        if (isset($filters['model_id'])) {
            $query->where('car_model_id', $filters['model_id']);
        }

        if (isset($filters['year_from'])) {
            $query->where('year', '>=', $filters['year_from']);
        }

        if (isset($filters['year_to'])) {
            $query->where('year', '<=', $filters['year_to']);
        }

        if (isset($filters['price_from'])) {
            $query->where('price', '>=', $filters['price_from']);
        }

        if (isset($filters['price_to'])) {
            $query->where('price', '<=', $filters['price_to']);
        }

        if (isset($filters['featured'])) {
            $query->where('is_featured', $filters['featured']);
        }

        if (isset($filters['city_id'])) {
            $this->applyCityFilter($query, (int) $filters['city_id']);
        }

        if (isset($filters['sort_by'])) {
            switch ($filters['sort_by']) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'featured':
                    $query->orderBy('is_featured', 'desc')
                          ->orderBy('featured_at', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 7);
    }

    /**
     * Get all studios (public endpoint) with their car listings
     */
    public function getAllStudios(array $filters = [])
    {
        $query = DealerStudio::where('is_active', true)
            ->where('is_verified', true)
            ->with([
                'dealer',
                'city',
                'carListings' => function ($query) use ($filters) {
                    $query->where('status', 'active')
                        ->with([
                            'brand:id,name,odoo_id',
                            'carModel:id,name,car_brand_id',
                            'bodyType:id,name',
                            'location:id,name',
                            'media:id,modelable_type,modelable_id,media,path_file_name,file_type,info,order',
                            'studio:id,city_id'
                        ])
                        ->when(isset($filters['city_id']), function ($carQuery) use ($filters) {
                            $this->applyCityFilter($carQuery, (int) $filters['city_id']);
                        })
                        ->orderBy('is_featured', 'desc')
                        ->orderBy('created_at', 'desc');
                }
            ]);

        // Filter by city if provided
        if (isset($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        // Filter by verified status (default: only verified)
        if (isset($filters['verified_only'])) {
            $query->where('is_verified', $filters['verified_only']);
        }

        // Sort options
        if (isset($filters['sort_by'])) {
            switch ($filters['sort_by']) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    /**
     * Get dealer's studios
     */
    public function getDealerStudios(User $dealer)
    {
        return DealerStudio::where('dealer_id', $dealer->id)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new studio for dealer
     */
    public function createStudio(User $dealer, array $data)
    {
        // Check if user is a dealer (either by subscription or approved application)
        if (!$this->isDealer($dealer)) {
            throw new \Exception('Only dealers can create studios');
        }

        return DealerStudio::create([
            'dealer_id' => $dealer->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'city_id' => $data['city_id'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'is_active' => true,
            'verification_status' => 'pending',
            'settings' => $data['settings'] ?? [],
            'social_links' => $data['social_links'] ?? [],
            'business_hours' => $data['business_hours'] ?? [],
            'amenities' => $data['amenities'] ?? [],
            'specialties' => $data['specialties'] ?? [],
        ]);
    }

    /**
     * Associate car listing with studio
     */
    public function associateListingWithStudio(CarListing $listing, int $studioId)
    {
        $studio = DealerStudio::find($studioId);
        
        if (!$studio || $studio->dealer_id !== $listing->user_id) {
            throw new \Exception('Invalid studio or permission denied');
        }

        $listing->update(['studio_id' => $studioId]);
        return $listing;
    }

    /**
     * Get free tier listings (basic listings only)
     */
    protected function getFreeTierListings(array $filters = [])
    {
        $query = CarListing::with(['brand', 'carModel', 'bodyType', 'location', 'media'])
            ->where('status', 'active')
            ->where('is_featured', false)
            ->where('is_sponsored', false);

        return $this->applyFilters($query, $filters)->paginate($filters['per_page'] ?? 7);
    }

    /**
     * Get Start plan listings
     */
    protected function getStartPlanListings(User $user, array $filters = [])
    {
        $query = CarListing::with(['brand', 'carModel', 'bodyType', 'location', 'media'])
            ->where('status', 'active');

        return $this->applyFilters($query, $filters)->paginate($filters['per_page'] ?? 7);
    }

    /**
     * Get Pro plan listings
     */
    protected function getProPlanListings(User $user, array $filters = [])
    {
        $query = CarListing::with(['brand', 'carModel', 'bodyType', 'location', 'media'])
            ->where('status', 'active');

        return $this->applyFilters($query, $filters)->paginate($filters['per_page'] ?? 7);
    }

    /**
     * Get Dealer plan listings
     */
    protected function getDealerPlanListings(User $user, array $filters = [])
    {
        $query = CarListing::with(['brand', 'carModel', 'bodyType', 'location', 'media', 'studio'])
            ->where('status', 'active');

        // If dealer has studios, prioritize their listings
        $studios = $this->getDealerStudios($user);
        if ($studios->isNotEmpty()) {
            $studioIds = $studios->pluck('id');
            $query->where(function ($q) use ($studioIds) {
                $q->whereIn('studio_id', $studioIds)
                  ->orWhere('user_id', $user->id);
            });
        }

        return $this->applyFilters($query, $filters)->paginate($filters['per_page'] ?? 7);
    }

    /**
     * Get dealer top picks
     */
    protected function getDealerTopPicks(User $user, int $limit, array $filters = [])
    {
        $studios = $this->getDealerStudios($user);
        
        if ($studios->isEmpty()) {
            return collect();
        }

        $studioIds = $studios->pluck('id');

        // Optimize query with limited column selection and specific media loading
        $query = CarListing::select('car_listings.*')
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'location:id,name',
                'media' => function($mediaQuery) {
                    $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->where('info', 'primary')
                        ->orderBy('order')
                        ->limit(1);
                },
                'studio:id,name,city_id',
                'user:id,full_name,email',
                'user.profilePictureMedia' => function($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info');
                }
            ])
            ->where('status', 'active')
            ->whereIn('studio_id', $studioIds)
            ->where(function ($query) {
                $query->where('is_featured', true)
                      ->orWhere('is_sponsored', true);
            });

        if (!empty($filters['city_id'])) {
            $this->applyCityFilter($query, (int) $filters['city_id']);
        }

        // Order by total views from analytics (subquery), then featured_at, then created_at
        // Map calculated views to 'views' attribute so the resource can access it
        return $query->leftJoin('car_listing_analytics', function($join) {
                $join->on('car_listings.id', '=', 'car_listing_analytics.car_listing_id')
                     ->where('car_listing_analytics.metric_type', '=', 'views');
            })
            ->selectRaw('COALESCE(SUM(car_listing_analytics.metric_value), 0) as views')
            ->groupBy('car_listings.id')
            ->orderBy('views', 'desc')
            ->orderBy('featured_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get general top picks (public method for public endpoint)
     */
    public function getGeneralTopPicks(int $limit, array $filters = [])
    {
        // Optimize query with limited column selection and specific media loading
        $query = CarListing::select('car_listings.*')
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'location:id,name',
                'media' => function($mediaQuery) {
                    $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->where('info', 'primary')
                        ->orderBy('order')
                        ->limit(1);
                },
                'studio:id,name,city_id',
                'user:id,full_name,email',
                'user.profilePictureMedia' => function($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info');
                }
            ])
            ->where('status', 'active')
            ->where('is_featured', true);

        if (!empty($filters['city_id'])) {
            $this->applyCityFilter($query, (int) $filters['city_id']);
        }

        // Order by total views from analytics (subquery), then featured_at, then created_at
        // Map calculated views to 'views' attribute so the resource can access it
        return $query->leftJoin('car_listing_analytics', function($join) {
                $join->on('car_listings.id', '=', 'car_listing_analytics.car_listing_id')
                     ->where('car_listing_analytics.metric_type', '=', 'views');
            })
            ->selectRaw('COALESCE(SUM(car_listing_analytics.metric_value), 0) as views')
            ->groupBy('car_listings.id')
            ->orderBy('views', 'desc')
            ->orderBy('featured_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get dealer recommendations
     */
    protected function getDealerRecommendations(User $user, CarListing $excludeListing, int $limit)
    {
        $studios = $this->getDealerStudios($user);
        
        if ($studios->isEmpty()) {
            return collect();
        }

        $studioIds = $studios->pluck('id');

        return CarListing::with(['brand', 'carModel', 'bodyType', 'location', 'media'])
            ->where('status', 'active')
            ->whereIn('studio_id', $studioIds)
            ->where('id', '!=', $excludeListing->id)
            ->where('car_brand_id', $excludeListing->car_brand_id)
            ->where('car_model_id', $excludeListing->car_model_id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get general recommendations
     */
    protected function getGeneralRecommendations(CarListing $excludeListing, int $limit)
    {
        return CarListing::with(['brand', 'carModel', 'bodyType', 'location', 'media'])
            ->where('status', 'active')
            ->where('id', '!=', $excludeListing->id)
            ->where('car_brand_id', $excludeListing->car_brand_id)
            ->where('car_model_id', $excludeListing->car_model_id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters)
    {
        if (isset($filters['brand_id'])) {
            $query->where('car_brand_id', $filters['brand_id']);
        }

        if (isset($filters['model_id'])) {
            $query->where('car_model_id', $filters['model_id']);
        }

        if (isset($filters['body_type_id'])) {
            $query->where('car_body_type_id', $filters['body_type_id']);
        }

        if (isset($filters['year_from'])) {
            $query->where('year', '>=', $filters['year_from']);
        }

        if (isset($filters['year_to'])) {
            $query->where('year', '<=', $filters['year_to']);
        }

        if (isset($filters['price_from'])) {
            $query->where('price', '>=', $filters['price_from']);
        }

        if (isset($filters['price_to'])) {
            $query->where('price', '<=', $filters['price_to']);
        }

        if (isset($filters['city_id'])) {
            $this->applyCityFilter($query, (int) $filters['city_id']);
        }

        if (isset($filters['mileage_from'])) {
            $query->where('mileage', '>=', $filters['mileage_from']);
        }

        if (isset($filters['mileage_to'])) {
            $query->where('mileage', '<=', $filters['mileage_to']);
        }

        if (isset($filters['featured'])) {
            $query->where('is_featured', $filters['featured']);
        }

        if (isset($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (isset($filters['sort_by'])) {
            switch ($filters['sort_by']) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'featured':
                    $query->orderBy('is_featured', 'desc')
                          ->orderBy('featured_at', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    /**
     * Apply a standardized city filter to car listing queries using Laravel city ID only.
     * Filters listings by checking if the listing's location name matches the city name.
     */
    protected function applyCityFilter($query, int $cityId)
    {
        if (!$cityId) {
            return;
        }

        // Get the city by Laravel ID
        $city = \App\Models\City::find($cityId);
        if (!$city) {
            // If city doesn't exist, return no results
            $query->whereRaw('1 = 0');
            return;
        }

        // Filter by checking if the listing's location name matches the city name
        // We filter by the location relationship's name matching the city name
        $query->whereHas('location', function ($locationQuery) use ($city) {
            $locationQuery->where('name', $city->name);
        });
    }
}
