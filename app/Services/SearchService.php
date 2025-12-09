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
use App\Models\MileageType;
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
     * Delete a specific search history record for a user
     */
    public function deleteSearchHistory(int $userId, int $searchId): bool
    {
        $deleted = DB::table('search_histories')
            ->where('user_id', $userId)
            ->where('id', $searchId)
            ->delete();

        if ($deleted) {
            for ($limit = 1; $limit <= 10; $limit++) {
                Cache::forget("recent_searches_{$userId}_{$limit}");
            }
        }

        return (bool) $deleted;
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
    public function getFilterOptions(?string $lang = null): array
    {
        // Use a versioned cache key to force refresh after code changes (include lang in cache key)
        $cacheKey = 'search_filter_options_v8_' . ($lang ?? 'en');
        return Cache::remember($cacheKey, 3600, function () use ($lang) {
            // Helper to get unique records by name using subquery
            $getUniqueByName = function($tableName, $translationFields = false) use ($lang) {
                $ids = DB::table($tableName)
                    ->select(DB::raw('MIN(id) as id'))
                    ->groupBy('name')
                    ->pluck('id')
                    ->toArray();
                
                $selectFields = ['id', 'name'];
                if ($translationFields) {
                    $selectFields[] = 'name_ar';
                    $selectFields[] = 'name_kr';
                }
                
                return DB::table($tableName)
                    ->select($selectFields)
                    ->whereIn('id', $ids)
                    ->orderBy('name')
                    ->get()
                    ->map(function($item) use ($lang) {
                        $name = $item->name;
                        if ($lang && isset($item->{'name_' . $lang}) && !empty($item->{'name_' . $lang})) {
                            $name = $item->{'name_' . $lang};
                        }
                        return [
                            'id' => $item->id,
                            'name' => $name
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

            // Get unique exterior colors (with translation support)
            $exteriorColors = $getUniqueByName('exterior_colors', true);

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
    public function searchListings(array $filters, int $page = 1, int $perPage = 20, ?string $lang = null): array
    {
        $sortBy = $filters['sort'] ?? 'latest';
        $cacheKey = 'search_listings_' . md5(serialize($filters)) . "_{$page}_{$perPage}_{$sortBy}_" . ($lang ?? 'en');
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $page, $perPage, $sortBy, $lang) {
            // Build base query for filtering (without eager loading for count)
            $baseQuery = CarListing::query();
            $this->applySearchFilters($baseQuery, $filters);

            // Get total count using a simplified query with only ID selection for better performance
            // Use a subquery to avoid loading all data
            $total = (clone $baseQuery)->select('car_listings.id')->distinct()->count('car_listings.id');

            // Now build the query with eager loading for actual results
            // Load all relationships needed for standardized format (including translation fields)
            $query = CarListing::with([
                'brand:id,name,name_ar,name_kr,odoo_id',
                'carModel:id,name,name_ar,name_kr,car_brand_id',
                'bodyType:id,name,name_ar,name_kr',
                'city:id,name,name_ar,name_kr,odoo_id',
                'media' => function($mediaQuery) {
                    $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                'inspection' => function($inspectionQuery) {
                    $inspectionQuery->select('id', 'car_listing_id', 'right_side', 'left_side', 'roof', 'front', 'back');
                },
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name,name_ar,name_kr',
                'driveType:id,name',
                'cylinder:id,name',
                'carCondition:id,name',
                'mileageType:id,name',
                'user:id,full_name',
                'user.profilePictureMedia' => function($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                        ->where('info', 'profile_picture');
                }
            ]);

            // Apply the same filters to the eager-loaded query
            $this->applySearchFilters($query, $filters);

            // Apply sorting - same as /api/car-listings
            switch ($sortBy) {
                case 'year_high':
                    $query->orderBy('car_listings.year', 'desc');
                    break;
                case 'year_low':
                    $query->orderBy('car_listings.year', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('car_listings.price', 'desc');
                    break;
                case 'price_low':
                    $query->orderBy('car_listings.price', 'asc');
                    break;
                case 'mileage_high':
                    $query->orderBy('car_listings.mileage', 'desc');
                    break;
                case 'mileage_low':
                    $query->orderBy('car_listings.mileage', 'asc');
                    break;
                default: // 'latest' or any other value
                    $query->orderBy('car_listings.created_at', 'desc');
            }

            // Get paginated results - use limit/offset for better performance
            $listings = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Format listings using shared formatter (matches /api/car-listings format)
            $formatter = app(\App\Services\CarListingFormatterService::class);
            $formattedListings = $formatter->formatListings($listings, null, $lang);

            return [
                'listings' => $formattedListings,
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
        // Base filters - always apply these
        // Only include active listings (exclude pending, sold, archived, suspended, expired)
        $query->where(function($q) {
            $q->where('status', 'active');
        })
        ->whereNull('sold_at')
        ->whereNull('archived_at');
        
        // Text search - optimized to reduce query complexity
        if (isset($filters['search']) && $filters['search'] !== '' && $filters['search'] !== null) {
            $search = trim($filters['search']);
            if (!empty($search)) {
                $searchLower = strtolower($search);
                
                // First, try to find matching brands and models to reduce subquery complexity
                // Search in all language fields
                $matchingBrandIds = \App\Models\CarBrand::where(function($q) use ($searchLower) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchLower . '%'])
                      ->orWhereRaw('LOWER(name_ar) LIKE ?', ['%' . $searchLower . '%'])
                      ->orWhereRaw('LOWER(name_kr) LIKE ?', ['%' . $searchLower . '%']);
                })
                    ->pluck('id')
                    ->toArray();
                
                $matchingModelIds = \App\Models\CarModel::where(function($q) use ($searchLower) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchLower . '%'])
                      ->orWhereRaw('LOWER(name_ar) LIKE ?', ['%' . $searchLower . '%'])
                      ->orWhereRaw('LOWER(name_kr) LIKE ?', ['%' . $searchLower . '%']);
                })
                    ->pluck('id')
                    ->toArray();
                
                $query->where(function($q) use ($searchLower, $matchingBrandIds, $matchingModelIds) {
                    $q->whereRaw('LOWER(title) LIKE ?', ['%' . $searchLower . '%']);
                    
                    if (!empty($matchingBrandIds)) {
                        $q->orWhereIn('car_brand_id', $matchingBrandIds);
                    }
                    
                    if (!empty($matchingModelIds)) {
                        $q->orWhereIn('car_model_id', $matchingModelIds);
                    }
                });
            }
        }

        // Brand filter - support both singular and plural
        $brandIds = $this->normalizeIds($filters['brand_ids'] ?? $filters['brand_id'] ?? null);
        if (!empty($brandIds)) {
            $query->whereIn('car_brand_id', $brandIds);
        }

        // Studio filter - support both singular and plural
        $studioIds = $this->normalizeIds($filters['studio_ids'] ?? $filters['studio_id'] ?? null);
        if (!empty($studioIds)) {
            $query->whereIn('studio_id', $studioIds);
        }

        // User filter - support both singular and plural
        $userIds = $this->normalizeIds($filters['user_ids'] ?? $filters['user_id'] ?? null);
        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        // Model filter - support both singular and plural
        $modelIds = $this->normalizeIds($filters['model_ids'] ?? $filters['model_id'] ?? null);
        if (!empty($modelIds)) {
            $query->whereIn('car_model_id', $modelIds);
        }

        // Condition filter - support both singular and plural
        $conditionIds = $this->normalizeIds($filters['condition_ids'] ?? $filters['condition_id'] ?? null);
        if (!empty($conditionIds)) {
            // Cache condition lookups to avoid query on every search
            $conditions = Cache::remember('car_conditions_' . md5(implode(',', $conditionIds)), 3600, function () use ($conditionIds) {
                return CarCondition::select('id', 'name')->whereIn('id', $conditionIds)->get();
            });

            if ($conditions->isNotEmpty()) {
                $conditionNames = $conditions->pluck('name')->map(fn($name) => strtolower($name))->toArray();

                $query->where(function($q) use ($conditionIds, $conditionNames) {
                    // Match by condition_id
                    $q->whereIn('car_condition_id', $conditionIds);
                    
                    // Also match by condition_type for legacy listings
                    if (!empty($conditionNames)) {
                        $q->orWhere(function($legacyQuery) use ($conditionNames) {
                            $legacyQuery->whereNull('car_condition_id');
                            foreach ($conditionNames as $index => $conditionName) {
                                if ($index === 0) {
                                    $legacyQuery->whereRaw('LOWER(condition_type) = ?', [$conditionName]);
                                } else {
                                    $legacyQuery->orWhereRaw('LOWER(condition_type) = ?', [$conditionName]);
                                }
                            }
                        });
                    }
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

        // Price range filter - support both individual and range with currency normalization
        // Base/filter currency is always USD:
        // - Listings with price_currency = 'USD' or NULL: compared directly
        // - Listings with price_currency = 'IQD': converted to USD using exchange rate
        $priceMin = isset($filters['price_min']) && $filters['price_min'] !== '' && $filters['price_min'] !== null ? (float)$filters['price_min'] : null;
        $priceMax = isset($filters['price_max']) && $filters['price_max'] !== '' && $filters['price_max'] !== null ? (float)$filters['price_max'] : null;
        $iqdPerUsd = $this->getIqdPerUsdRate();

        if ($priceMin !== null || $priceMax !== null) {
            $query->where(function($q) use ($priceMin, $priceMax, $iqdPerUsd) {
                // USD (or NULL) currency listings: compare directly
                $q->where(function($usdQ) use ($priceMin, $priceMax) {
                    $usdQ->where(function($currencyQ) {
                        $currencyQ->where('price_currency', 'USD')
                                  ->orWhereNull('price_currency');
                    });

                    if ($priceMin !== null && $priceMax !== null) {
                        $usdQ->whereBetween('price', [$priceMin, $priceMax]);
                    } elseif ($priceMin !== null) {
                        $usdQ->where('price', '>=', $priceMin);
                    } elseif ($priceMax !== null) {
                        $usdQ->where('price', '<=', $priceMax);
                    }
                });

                // IQD listings: convert to USD for comparison
                $q->orWhere(function($iqdQ) use ($priceMin, $priceMax, $iqdPerUsd) {
                    $iqdQ->where('price_currency', 'IQD');

                    if ($priceMin !== null && $priceMax !== null) {
                        $iqdQ->whereRaw('(price / ?) BETWEEN ? AND ?', [$iqdPerUsd, $priceMin, $priceMax]);
                    } elseif ($priceMin !== null) {
                        $iqdQ->whereRaw('(price / ?) >= ?', [$iqdPerUsd, $priceMin]);
                    } elseif ($priceMax !== null) {
                        $iqdQ->whereRaw('(price / ?) <= ?', [$iqdPerUsd, $priceMax]);
                    }
                });
            });
        }

        // Mileage range filter - support both individual and range (FIXED: properly handle 0 values)
        $mileageMin = isset($filters['mileage_min']) && $filters['mileage_min'] !== '' && $filters['mileage_min'] !== null ? (int)$filters['mileage_min'] : null;
        $mileageMax = isset($filters['mileage_max']) && $filters['mileage_max'] !== '' && $filters['mileage_max'] !== null ? (int)$filters['mileage_max'] : null;
        
        // If mileage filters are provided, always use unit conversion (default to 'km' if no unit specified)
        if ($mileageMin !== null || $mileageMax !== null) {
            // Default requested mileage unit to 'km' when any mileage filter is provided and no unit specified
            $requestedMileageUnit = $this->normalizeMileageUnit(
                $filters['mileage_unit'] ?? $filters['mileage_units'] ?? 'km'
            );
            
            // If unit is still null after normalization (shouldn't happen due to validation, but safety check)
            // Default to 'km' to ensure unit conversion is always applied
            if ($requestedMileageUnit === null) {
                $requestedMileageUnit = 'km';
            }
            
            $this->applyMileageFilterWithUnit($query, $mileageMin, $mileageMax, $requestedMileageUnit);
        }

        // Body type filter
        $bodyTypeIds = $this->normalizeIds($filters['body_type_ids'] ?? $filters['body_type_id'] ?? null);
        if (!empty($bodyTypeIds)) {
            $query->whereIn('car_body_type_id', $bodyTypeIds);
        }

        // Fuel type filter
        $fuelTypeIds = $this->normalizeIds($filters['fuel_type_ids'] ?? $filters['fuel_type_id'] ?? null);
        if (!empty($fuelTypeIds)) {
            $query->whereIn('fuel_type_id', $fuelTypeIds);
        }

        // Transmission filter
        $transmissionIds = $this->normalizeIds($filters['transmission_ids'] ?? $filters['transmission_id'] ?? null);
        if (!empty($transmissionIds)) {
            $query->whereIn('transmission_id', $transmissionIds);
        }

        // Exterior color filter
        $exteriorColorIds = $this->normalizeIds($filters['exterior_color_ids'] ?? $filters['exterior_color_id'] ?? null);
        if (!empty($exteriorColorIds)) {
            $query->whereIn('exterior_color_id', $exteriorColorIds);
        }

        // Drive type filter
        $driveTypeIds = $this->normalizeIds($filters['drive_type_ids'] ?? $filters['drive_type_id'] ?? null);
        if (!empty($driveTypeIds)) {
            $query->whereIn('drive_type_id', $driveTypeIds);
        }

        // Cylinder filter
        $cylinderIds = $this->normalizeIds($filters['cylinder_ids'] ?? $filters['cylinder_id'] ?? null);
        if (!empty($cylinderIds)) {
            $query->whereIn('cylinder_id', $cylinderIds);
        }

        // City filter
        $cityIds = $this->normalizeIds($filters['city_ids'] ?? $filters['city_id'] ?? null);
        if (!empty($cityIds)) {
            $query->whereIn('city_id', $cityIds);
        }

        // Import type filter - support both singular and plural
        $importTypeIds = $this->normalizeIds($filters['import_type_ids'] ?? $filters['import_type_id'] ?? null);
        if (!empty($importTypeIds)) {
            $query->whereIn('import_type_id', $importTypeIds);
        }

        // Warranty filter
        if (isset($filters['has_warranty'])) {
            // Handle both boolean and string values
            $warrantyValue = $filters['has_warranty'];
            if (is_string($warrantyValue)) {
                $normalized = strtolower(trim($warrantyValue));
                // Map string values to boolean
                if (in_array($normalized, ['true', '1', 'yes'], true)) {
                    $warrantyValue = true;
                } elseif (in_array($normalized, ['false', '0', 'no'], true)) {
                    $warrantyValue = false;
                } else {
                    // Default to false for unknown string values
                    $warrantyValue = false;
                }
            } elseif (is_numeric($warrantyValue)) {
                $warrantyValue = (int)$warrantyValue === 1;
            }
            // Convert to boolean and apply filter
            $query->where('has_warranty', (bool)$warrantyValue);
        }
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

    private function normalizeIds($value): array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value), fn ($id) => $id !== 0));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(array_map('intval', explode(',', $value)), fn ($id) => $id !== 0));
        }

        return [(int) $value];
    }

    private function normalizeValues($value): array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value), fn ($item) => $item !== ''));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(array_map(fn ($item) => trim((string) $item), explode(',', $value)), fn ($item) => $item !== ''));
        }

        return [trim((string) $value)];
    }

    private function normalizeMileageUnit($value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === null) {
            return null;
        }

        // Handle numeric values: 1 = km, 2 = mile
        if (is_numeric($value)) {
            $numericValue = (int) $value;
            return match ($numericValue) {
                1 => 'km',
                2 => 'mile',
                default => null,
            };
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'km', 'kilometer', 'kilometers', 'kilometre', 'kilometres', '1' => 'km',
            'mile', 'miles', 'mi', '2' => 'mile',
            default => null,
        };
    }

    private function applyMileageFilterWithUnit($query, ?int $mileageMin, ?int $mileageMax, string $requestedUnit): void
    {
        $typeMap = $this->getMileageTypeMap();
        $kmTypeId = $typeMap['km'] ?? null;
        $mileTypeId = $typeMap['mile'] ?? null;

        $kmMin = $mileageMin !== null ? $this->convertMileage($mileageMin, $requestedUnit, 'km') : null;
        $kmMax = $mileageMax !== null ? $this->convertMileage($mileageMax, $requestedUnit, 'km') : null;
        $mileMin = $mileageMin !== null ? $this->convertMileage($mileageMin, $requestedUnit, 'mile') : null;
        $mileMax = $mileageMax !== null ? $this->convertMileage($mileageMax, $requestedUnit, 'mile') : null;

        $query->where(function ($outer) use ($kmTypeId, $kmMin, $kmMax, $mileTypeId, $mileMin, $mileMax, $requestedUnit) {
            if ($kmTypeId) {
                $outer->orWhere(function ($inner) use ($kmTypeId, $kmMin, $kmMax) {
                    $inner->where('mileage_type_id', $kmTypeId);

                    if ($kmMin !== null) {
                        $inner->where('mileage', '>=', $kmMin);
                    }

                    if ($kmMax !== null) {
                        $inner->where('mileage', '<=', $kmMax);
                    }
                });
            }

            if ($mileTypeId) {
                $outer->orWhere(function ($inner) use ($mileTypeId, $mileMin, $mileMax) {
                    $inner->where('mileage_type_id', $mileTypeId);

                    if ($mileMin !== null) {
                        $inner->where('mileage', '>=', $mileMin);
                    }

                    if ($mileMax !== null) {
                        $inner->where('mileage', '<=', $mileMax);
                    }
                });
            }

            // Listings without mileage_type_id should match if they fall within either unit's range
            // This handles cases where the mileage could be in either unit
            $outer->orWhere(function ($inner) use ($kmMin, $kmMax, $mileMin, $mileMax, $requestedUnit) {
                $inner->whereNull('mileage_type_id');

                // Build conditions for both possible units
                $inner->where(function ($unitCheck) use ($kmMin, $kmMax, $mileMin, $mileMax, $requestedUnit) {
                    // Check as if mileage is in km
                    if ($kmMin !== null || $kmMax !== null) {
                        $unitCheck->where(function ($kmCheck) use ($kmMin, $kmMax) {
                            if ($kmMin !== null) {
                                $kmCheck->where('mileage', '>=', $kmMin);
                            }
                            if ($kmMax !== null) {
                                $kmCheck->where('mileage', '<=', $kmMax);
                            }
                        });
                    }

                    // Check as if mileage is in miles
                    if ($mileMin !== null || $mileMax !== null) {
                        $unitCheck->orWhere(function ($mileCheck) use ($mileMin, $mileMax) {
                            if ($mileMin !== null) {
                                $mileCheck->where('mileage', '>=', $mileMin);
                            }
                            if ($mileMax !== null) {
                                $mileCheck->where('mileage', '<=', $mileMax);
                            }
                        });
                    }
                });
            });
        });
    }

    private function convertMileage(float|int $value, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return (float) $value;
        }

        $conversionFactor = 1.60934; // kilometers in a mile

        return match ([$fromUnit, $toUnit]) {
            ['km', 'mile'] => (float) $value / $conversionFactor,
            ['mile', 'km'] => (float) $value * $conversionFactor,
            default => (float) $value,
        };
    }

    private function getMileageTypeMap(): array
    {
        // Use Laravel cache instead of static variable for better performance across requests
        return Cache::remember('mileage_type_map', 3600, function () {
            $cache = [];

            MileageType::query()->select('id', 'name')->get()->each(function ($type) use (&$cache) {
                $normalized = strtolower($type->name);

                if (in_array($normalized, ['km', 'kilometer', 'kilometers', 'kilometre', 'kilometres'], true)) {
                    $cache['km'] = $type->id;
                } elseif (in_array($normalized, ['mile', 'miles', 'mi'], true)) {
                    $cache['mile'] = $type->id;
                }
            });

            return $cache;
        });
    }

    /**
     * Get IQD per 1 USD exchange rate used for normalization.
     * Falls back to 1300 if not configured.
     */
    private function getIqdPerUsdRate(): float
    {
        // Prefer config, then env, fallback constant
        $rate = config('app.iqd_per_usd') ?? env('IQD_PER_USD', 1300);
        $rate = (float) $rate;
        return $rate > 0 ? $rate : 1300.0;
    }
}
