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
use App\Models\ImportType;
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
                ->orderByRaw('COALESCE(last_searched_at, updated_at, created_at) DESC')
                ->limit($limit)
                ->get([
                    'id',
                    'title',
                    'search_query',
                    'filters',
                    'sort_options',
                    'is_saved',
                    'notifications_enabled',
                    'search_count',
                    'last_searched_at',
                    'created_at',
                    'updated_at',
                ])
                ->map(function ($item) {
                    return [
                        'id' => (int) $item->id,
                        'title' => $item->title,
                        'search_query' => $item->search_query,
                        'filters' => $item->filters ? json_decode($item->filters, true) : null,
                        'sort_options' => $item->sort_options ? json_decode($item->sort_options, true) : null,
                        'is_saved' => (bool) $item->is_saved,
                        'notifications_enabled' => (bool) $item->notifications_enabled,
                        'search_count' => (int) $item->search_count,
                        'last_searched_at' => $item->last_searched_at,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Save search term to user's history
     */
    public function saveSearchTerm(int $userId, string $searchTerm): void
    {
        $now = now();

        DB::transaction(function () use ($userId, $searchTerm, $now) {
            $existing = DB::table('search_histories')
                ->where('user_id', $userId)
                ->where('title', $searchTerm)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                DB::table('search_histories')
                    ->where('id', $existing->id)
                    ->update([
                        'search_query' => $searchTerm,
                        'search_count' => DB::raw('search_count + 1'),
                        'last_searched_at' => $now,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('search_histories')->insert([
                    'user_id' => $userId,
                    'title' => $searchTerm,
                    'search_query' => $searchTerm,
                    'filters' => null,
                    'sort_options' => null,
                    'is_saved' => false,
                    'notifications_enabled' => false,
                    'search_count' => 1,
                    'last_searched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        // Clear cached recent searches (limits 1-10 are allowed by validation)
        for ($limit = 1; $limit <= 10; $limit++) {
            Cache::forget("recent_searches_{$userId}_{$limit}");
        }
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
        // Use a versioned cache key to force refresh after code changes
        return Cache::remember('search_filter_options_v7', 3600, function () {
            // Helper to get unique records by name using subquery
            $getUniqueByName = function($tableName) {
                $ids = DB::table($tableName)
                    ->select(DB::raw('MIN(id) as id'))
                    ->groupBy('name')
                    ->pluck('id')
                    ->toArray();
                
                return DB::table($tableName)
                    ->select('id', 'name')
                    ->whereIn('id', $ids)
                    ->orderBy('name')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name
                        ];
                    })
                    ->toArray();
            };

            // Get unique conditions
            $conditions = $getUniqueByName('car_conditions');

            // Get unique fuel types
            $fuelTypes = $getUniqueByName('fuel_types');

            // Get unique transmissions
            $transmissions = $getUniqueByName('transmissions');

            // Get unique exterior colors
            $exteriorColors = $getUniqueByName('exterior_colors');

            // Get unique drive types
            $driveTypes = $getUniqueByName('drive_types');

            // Get unique cylinders with special sorting
            $cylinderIds = DB::table('cylinder_options')
                ->select(DB::raw('MIN(id) as id'))
                ->groupBy('name')
                ->pluck('id')
                ->toArray();
            
            $cylinders = DB::table('cylinder_options')
                ->select('id', 'name')
                ->whereIn('id', $cylinderIds)
                ->get()
                ->sortBy(function($item) {
                    // Sort numerically first, then Non-Electric, then Unknown
                    if (is_numeric($item->name)) {
                        return (int)$item->name;
                    } elseif (strtolower($item->name) === 'non-electric') {
                        return 999;
                    } elseif (strtolower($item->name) === 'unknown') {
                        return 1000;
                    } else {
                        return 998;
                    }
                })
                ->values()
                ->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name
                    ];
                })
                ->toArray();

            // Body types with media - get unique IDs first, then load with relationships
            $bodyTypeIds = DB::table('body_types')
                ->select(DB::raw('MIN(id) as id'))
                ->groupBy('name')
                ->pluck('id')
                ->toArray();
            
            $bodyTypes = BodyType::with(['medias', 'media'])
                ->whereIn('id', $bodyTypeIds)
                ->orderBy('name')
                ->get()
                ->map(function($item) {
                    $media = null;
                    
                    // First try the singular media relationship (with info='main' filter)
                    if ($item->relationLoaded('media') && $item->media) {
                        $mediaRelation = $item->media;
                        $media = $mediaRelation->media_link ?? $mediaRelation->public_url ?? null;
                    }
                    // Fallback to medias collection if singular media is not available
                    elseif ($item->relationLoaded('medias') && $item->medias && $item->medias->isNotEmpty()) {
                        // Get the first media (or the one with info='main' if exists)
                        $mediaItem = $item->medias->firstWhere('info', 'main') ?? $item->medias->first();
                        
                        if ($mediaItem) {
                            $media = $mediaItem->media_link ?? $mediaItem->public_url ?? null;
                        }
                    }
                    
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'is_active' => $item->is_active,
                        'odoo_id' => $item->odoo_id,
                        'media' => $media
                    ];
                })
                ->toArray();

            // Cities - get unique IDs first, then load
            $cityIds = DB::table('cities')
                ->select(DB::raw('MIN(id) as id'))
                ->groupBy('name')
                ->pluck('id')
                ->toArray();
            
            $cities = City::whereIn('id', $cityIds)
                ->orderBy('name')
                ->get()
                ->map(function($item) {
                    return [
                        'id' => $item->id,
                        'odoo_id' => $item->odoo_id,
                        'name' => $item->name
                    ];
                })
                ->toArray();

            // Get unique import types (GCC/IMPORT)
            $importTypes = $getUniqueByName('import_types');

            // Warranty options (Yes/No)
            $warrantyOptions = [
                ['value' => true, 'label' => 'Yes'],
                ['value' => false, 'label' => 'No']
            ];

            // Mileage units (KM/Mile)
            $mileageUnits = [
                ['value' => 'km', 'label' => 'KM'],
                ['value' => 'mile', 'label' => 'Mile']
            ];

            // Calculate ranges from active, non-sold listings only
            $ranges = CarListing::where('status', 'active')
                ->whereNull('sold_at')
                ->selectRaw('MIN(year) as year_min, MAX(year) as year_max, MIN(price) as price_min, MAX(price) as price_max, MIN(mileage) as mileage_min, MAX(mileage) as mileage_max')
                ->first();

            return [
                'conditions' => $conditions,
                'body_types' => $bodyTypes,
                'fuel_types' => $fuelTypes,
                'transmissions' => $transmissions,
                'exterior_colors' => $exteriorColors,
                'drive_types' => $driveTypes,
                'cylinders' => $cylinders,
                'cities' => $cities,
                'import_types' => $importTypes,
                'warranty_options' => $warrantyOptions,
                'mileage_units' => $mileageUnits,
                'year_range' => [
                    'min' => $ranges && $ranges->year_min ? (int)$ranges->year_min : null,
                    'max' => $ranges && $ranges->year_max ? (int)$ranges->year_max : null
                ],
                'price_range' => [
                    'min' => $ranges && $ranges->price_min ? (float)$ranges->price_min : null,
                    'max' => $ranges && $ranges->price_max ? (float)$ranges->price_max : null
                ],
                'mileage_range' => [
                    'min' => $ranges && $ranges->mileage_min ? (int)$ranges->mileage_min : null,
                    'max' => $ranges && $ranges->mileage_max ? (int)$ranges->mileage_max : null
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
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'location:id,name',
                'media:id,modelable_type,modelable_id,media,path_file_name,file_type,info,order',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                'carCondition:id,name',
                'user' => function ($query) {
                    $query->selectRaw('id, full_name, full_name as display_name, profile_picture, is_verified, is_dealer')
                          ->with('profilePictureMedia');
                }
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
        if (isset($filters['search']) && $filters['search'] !== '' && $filters['search'] !== null) {
            $search = trim($filters['search']);
            if (!empty($search)) {
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
        }

        // Brand filter
        if (isset($filters['brand_id']) && $filters['brand_id'] !== '' && $filters['brand_id'] !== null) {
            $query->where('car_brand_id', (int)$filters['brand_id']);
        }

        // Model filter
        if (isset($filters['model_id']) && $filters['model_id'] !== '' && $filters['model_id'] !== null) {
            $query->where('car_model_id', (int)$filters['model_id']);
        }

        // Condition filter
        if (isset($filters['condition_id']) && $filters['condition_id'] !== '' && $filters['condition_id'] !== null) {
            $conditionId = (int)$filters['condition_id'];
            $condition = CarCondition::find($conditionId);

            if ($condition) {
                $conditionName = strtolower($condition->name);

                $query->where(function($q) use ($conditionId, $conditionName) {
                    $q->where('car_condition_id', $conditionId)
                      ->orWhere(function($legacyQuery) use ($conditionName) {
                          $legacyQuery->whereNull('car_condition_id')
                                      ->whereRaw('LOWER(condition_type) = ?', [$conditionName]);
                      });
                });
            }
        }

        // Year range filter - support both individual and range
        $yearFrom = isset($filters['year_from']) && $filters['year_from'] !== '' && $filters['year_from'] !== null ? (int)$filters['year_from'] : null;
        $yearTo = isset($filters['year_to']) && $filters['year_to'] !== '' && $filters['year_to'] !== null ? (int)$filters['year_to'] : null;
        
        if ($yearFrom !== null && $yearTo !== null) {
            $query->whereBetween('year', [$yearFrom, $yearTo]);
        } elseif ($yearFrom !== null) {
            $query->where('year', '>=', $yearFrom);
        } elseif ($yearTo !== null) {
            $query->where('year', '<=', $yearTo);
        }

        // Price range filter - support both individual and range
        $priceMin = isset($filters['price_min']) && $filters['price_min'] !== '' && $filters['price_min'] !== null ? (float)$filters['price_min'] : null;
        $priceMax = isset($filters['price_max']) && $filters['price_max'] !== '' && $filters['price_max'] !== null ? (float)$filters['price_max'] : null;
        
        if ($priceMin !== null && $priceMax !== null) {
            $query->whereBetween('price', [$priceMin, $priceMax]);
        } elseif ($priceMin !== null) {
            $query->where('price', '>=', $priceMin);
        } elseif ($priceMax !== null) {
            $query->where('price', '<=', $priceMax);
        }

        // Mileage range filter - support both individual and range (FIXED: properly handle 0 values)
        $mileageMin = isset($filters['mileage_min']) && $filters['mileage_min'] !== '' && $filters['mileage_min'] !== null ? (int)$filters['mileage_min'] : null;
        $mileageMax = isset($filters['mileage_max']) && $filters['mileage_max'] !== '' && $filters['mileage_max'] !== null ? (int)$filters['mileage_max'] : null;
        
        if ($mileageMin !== null && $mileageMax !== null) {
            $query->whereBetween('mileage', [$mileageMin, $mileageMax]);
        } elseif ($mileageMin !== null) {
            $query->where('mileage', '>=', $mileageMin);
        } elseif ($mileageMax !== null) {
            $query->where('mileage', '<=', $mileageMax);
        }

        // Body type filter
        if (isset($filters['body_type_id']) && $filters['body_type_id'] !== '' && $filters['body_type_id'] !== null) {
            $query->where('car_body_type_id', (int)$filters['body_type_id']);
        }

        // Fuel type filter
        if (isset($filters['fuel_type_id']) && $filters['fuel_type_id'] !== '' && $filters['fuel_type_id'] !== null) {
            $query->where('fuel_type_id', (int)$filters['fuel_type_id']);
        }

        // Transmission filter
        if (isset($filters['transmission_id']) && $filters['transmission_id'] !== '' && $filters['transmission_id'] !== null) {
            $query->where('transmission_id', (int)$filters['transmission_id']);
        }

        // Exterior color filter
        if (isset($filters['exterior_color_id']) && $filters['exterior_color_id'] !== '' && $filters['exterior_color_id'] !== null) {
            $query->where('exterior_color_id', (int)$filters['exterior_color_id']);
        }

        // Drive type filter
        if (isset($filters['drive_type_id']) && $filters['drive_type_id'] !== '' && $filters['drive_type_id'] !== null) {
            $query->where('drive_type_id', (int)$filters['drive_type_id']);
        }

        // Cylinder filter
        if (isset($filters['cylinder_id']) && $filters['cylinder_id'] !== '' && $filters['cylinder_id'] !== null) {
            $query->where('cylinder_id', (int)$filters['cylinder_id']);
        }

        // City filter
        if (isset($filters['city_id']) && $filters['city_id'] !== '' && $filters['city_id'] !== null) {
            $cityId = (int)$filters['city_id'];
            $city = City::find($cityId);
            
            if ($city) {
                // Filter by matching location name with city name
                // Since locations are created with the same name as cities
                $query->whereHas('location', function($q) use ($city) {
                    $q->where('name', $city->name);
                });
            }
        }

        // Import type filter (GCC/IMPORT)
        if (isset($filters['import_type_id']) && $filters['import_type_id'] !== '' && $filters['import_type_id'] !== null) {
            $query->where('import_type_id', (int)$filters['import_type_id']);
        }

        // Warranty filter
        if (isset($filters['has_warranty'])) {
            // Handle both boolean and string values
            $warrantyValue = $filters['has_warranty'];
            if (is_string($warrantyValue)) {
                $warrantyValue = strtolower($warrantyValue) === 'true' || strtolower($warrantyValue) === '1' || strtolower($warrantyValue) === 'yes';
            }
            $query->where('has_warranty', $warrantyValue);
        }

        // Only active listings
        $query->where('status', 'active')->whereNull('sold_at');
    }

    /**
     * Clear search-related caches
     */
    public function clearSearchCache(): void
    {
        $keys = [
            'search_filter_options',
            'search_filter_options_v2',
            'search_filter_options_v3',
            'search_filter_options_v4',
            'search_filter_options_v5',
            'search_filter_options_v6',
            'search_filter_options_v7',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Note: Cache::flush() clears all cache, which may be too aggressive
        // Individual cache keys are handled above, pattern-based clearing
        // would require a cache driver that supports tag-based clearing
    }
}
