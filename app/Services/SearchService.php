<?php

namespace App\Services;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarListing;
use App\Models\BodyType;
use App\Models\FuelType;
use App\Models\Transmission;
use App\Models\ExteriorColor;
use App\Models\DriveType;
use App\Models\City;
use App\Models\CylinderOption;
use App\Models\CarCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SearchService
{
    /**
     * Search for car brands and models
     */
    public function searchBrandsAndModels(string $query, int $limit = 10): array
    {
        $cacheKey = "search_brands_models_{$query}_{$limit}";
        
        return Cache::remember($cacheKey, 300, function () use ($query, $limit) {
            // Search brands
            $brands = CarBrand::with(['media', 'carModels'])
                ->where('name', 'like', "%{$query}%")
                ->withCount('listings')
                ->orderBy('name')
                ->limit($limit)
                ->get();

            // Search models with brand information
            $models = CarModel::with(['brand.media', 'brand'])
                ->where('name', 'like', "%{$query}%")
                ->withCount('listings')
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return [
                'brands' => $brands,
                'models' => $models,
                'total_brands' => $brands->count(),
                'total_models' => $models->count(),
            ];
        });
    }

    /**
     * Get search suggestions for a specific brand
     */
    public function getBrandSearchSuggestions(string $brandName): array
    {
        $cacheKey = "brand_suggestions_{$brandName}";
        
        return Cache::remember($cacheKey, 600, function () use ($brandName) {
            $brand = CarBrand::with(['carModels'])
                ->where('name', 'like', "%{$brandName}%")
                ->first();

            if (!$brand) {
                return [
                    'brand' => null,
                    'models' => [],
                    'total_listings' => 0
                ];
            }

            $models = $brand->carModels()
                ->withCount('listings')
                ->orderBy('name')
                ->get();

            $totalListings = $brand->listings()->count();

            return [
                'brand' => $brand,
                'models' => $models,
                'total_listings' => $totalListings
            ];
        });
    }

    /**
     * Get recent searches for a user
     */
    public function getRecentSearches(int $userId, int $limit = 5): array
    {
        return Cache::remember("recent_searches_{$userId}_{$limit}", 300, function () use ($userId, $limit) {
            return DB::table('search_histories')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->pluck('search_term')
                ->toArray();
        });
    }

    /**
     * Save search term to user's history
     */
    public function saveSearchTerm(int $userId, string $searchTerm): void
    {
        DB::table('search_histories')->updateOrInsert(
            ['user_id' => $userId, 'search_term' => $searchTerm],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // Clear cache for recent searches
        Cache::forget("recent_searches_{$userId}_5");
    }

    /**
     * Get sponsored car listings for "You May Also Like" section
     */
    public function getSponsoredListings(int $limit = 2): array
    {
        return Cache::remember("sponsored_listings_{$limit}", 900, function () use ($limit) {
            return CarListing::with([
                'brand',
                'carModel',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%')->limit(1);
                }
            ])
            ->where('is_sponsored', true)
            ->where('status', 'active')
            ->whereNull('sold_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
        });
    }

    /**
     * Get all car brands with their logos and listing counts
     */
    public function getAllBrandsWithCounts(): array
    {
        return Cache::remember('all_brands_with_counts', 1800, function () {
            return CarBrand::with(['media'])
                ->withCount('listings')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get filter options for search
     */
    public function getFilterOptions(): array
    {
        return Cache::remember('search_filter_options', 3600, function () {
            return [
                'conditions' => CarCondition::orderBy('name')->get()->toArray(),
                'body_types' => BodyType::with(['media'])->orderBy('name')->get()->toArray(),
                'fuel_types' => FuelType::orderBy('name')->get()->toArray(),
                'transmissions' => Transmission::orderBy('name')->get()->toArray(),
                'exterior_colors' => ExteriorColor::orderBy('name')->get()->toArray(),
                'drive_types' => DriveType::orderBy('name')->get()->toArray(),
                'cylinders' => CylinderOption::orderBy('value')->get()->toArray(),
                'cities' => City::orderBy('name')->get()->toArray(),
                'year_range' => [
                    'min' => CarListing::min('year') ?? 1990,
                    'max' => CarListing::max('year') ?? date('Y')
                ],
                'price_range' => [
                    'min' => CarListing::min('price') ?? 0,
                    'max' => CarListing::max('price') ?? 100000
                ],
                'mileage_range' => [
                    'min' => CarListing::min('mileage') ?? 0,
                    'max' => CarListing::max('mileage') ?? 500000
                ]
            ];
        });
    }

    /**
     * Search car listings with advanced filters
     */
    public function searchListings(array $filters, int $page = 1, int $perPage = 20): array
    {
        $cacheKey = 'search_listings_' . md5(serialize($filters)) . "_{$page}_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $page, $perPage) {
            $query = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'location',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%');
                },
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder',
                'carCondition'
            ]);

            // Apply filters
            $this->applySearchFilters($query, $filters);

            $total = $query->count();
            $listings = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->orderBy('created_at', 'desc')
                ->get();

            return [
                'listings' => $listings,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
                'has_more_pages' => $page < ceil($total / $perPage)
            ];
        });
    }

    /**
     * Apply search filters to query
     */
    private function applySearchFilters($query, array $filters): void
    {
        // Text search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('brand', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('carModel', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Brand filter
        if (!empty($filters['brand_id'])) {
            $query->where('car_brand_id', $filters['brand_id']);
        }

        // Model filter
        if (!empty($filters['model_id'])) {
            $query->where('car_model_id', $filters['model_id']);
        }

        // Condition filter
        if (!empty($filters['condition'])) {
            $query->where('condition_type', $filters['condition']);
        }

        // Year range filter
        if (!empty($filters['year_from']) && !empty($filters['year_to'])) {
            $query->whereBetween('year', [$filters['year_from'], $filters['year_to']]);
        }

        // Price range filter
        if (!empty($filters['price_min']) && !empty($filters['price_max'])) {
            $query->whereBetween('price', [$filters['price_min'], $filters['price_max']]);
        }

        // Mileage range filter
        if (!empty($filters['mileage_min']) && !empty($filters['mileage_max'])) {
            $query->whereBetween('mileage', [$filters['mileage_min'], $filters['mileage_max']]);
        }

        // Body type filter
        if (!empty($filters['body_type_id'])) {
            $query->where('car_body_type_id', $filters['body_type_id']);
        }

        // Fuel type filter
        if (!empty($filters['fuel_type_id'])) {
            $query->where('fuel_type_id', $filters['fuel_type_id']);
        }

        // Transmission filter
        if (!empty($filters['transmission_id'])) {
            $query->where('transmission_id', $filters['transmission_id']);
        }

        // Exterior color filter
        if (!empty($filters['exterior_color_id'])) {
            $query->where('exterior_color_id', $filters['exterior_color_id']);
        }

        // Drive type filter
        if (!empty($filters['drive_type_id'])) {
            $query->where('drive_type_id', $filters['drive_type_id']);
        }

        // Cylinder filter
        if (!empty($filters['cylinder_id'])) {
            $query->where('cylinder_id', $filters['cylinder_id']);
        }

        // City filter
        if (!empty($filters['city_id'])) {
            $query->whereHas('location', function($q) use ($filters) {
                $q->where('city_id', $filters['city_id']);
            });
        }

        // Warranty filter
        if (isset($filters['has_warranty'])) {
            $query->where('has_warranty', $filters['has_warranty']);
        }

        // Only active listings
        $query->where('status', 'active')->whereNull('sold_at');
    }

    /**
     * Clear search-related caches
     */
    public function clearSearchCache(): void
    {
        $patterns = [
            'search_brands_models_*',
            'brand_suggestions_*',
            'recent_searches_*',
            'sponsored_listings_*',
            'all_brands_with_counts',
            'search_filter_options',
            'search_listings_*'
        ];

        foreach ($patterns as $pattern) {
            Cache::flush();
        }
    }
}
