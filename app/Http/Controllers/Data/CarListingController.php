<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\CarListing;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarTrim;
use App\Models\NewsArticle;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CarListingController extends Controller
{
    use ApiResponse;

    /**
     * Get car listings with filters and sorting
     */
    public function getCarListings(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'car_listings_' . md5(serialize($request->all()));

            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                return $this->successResponse($cachedResult, 'Car listings retrieved from cache');
            }

            // Select only essential fields for listing card UI
            $query = CarListing::select([
                'id', 'odoo_id', 'title', 'price', 'price_currency', 'year', 'trim', 'doors',
                'mileage', 'city_id', 'car_brand_id', 'car_body_type_id', 'car_model_id',
                'car_condition_id', 'fuel_type_id', 'transmission_id', 'exterior_color_id',
                'drive_type_id', 'cylinder_id', 'studio_id', 'is_sponsored', 'is_featured',
                'status', 'status_id', 'operator_decision', 'is_new_arrival', 'description',
                'has_warranty', 'is_inspected', 'is_verified', 'has_insurance',
                'origin_country', 'gcc_import', 'plate_status', 'condition_type',
                'featured_at', 'created_at', 'user_id'
            ])
            ->where('status', 'active') // Filter active only
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'city:id,name,odoo_id',
                // Load all media for car listing
                'media' => function($mediaQuery) {
                    $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                // Load inspection sides only (minimal data)
                'inspection' => function($inspectionQuery) {
                    $inspectionQuery->select('id', 'car_listing_id', 'right_side', 'left_side', 'roof', 'front', 'back');
                },
                'carCondition:id,name',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                // User with profile picture
                'user:id,full_name',
                'user.profilePictureMedia' => function($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                        ->where('info', 'profile_picture');
                }
            ]);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
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

            if ($request->filled('condition')) {
                $query->where('condition_type', $request->condition);
            }

            if ($request->filled('brand_id')) {
                $query->where('car_brand_id', $request->brand_id);
            }

            if ($request->filled('year_from') && $request->filled('year_to')) {
                $query->byYearRange($request->year_from, $request->year_to);
            }

            if ($request->filled('price_min') && $request->filled('price_max')) {
                $query->byPriceRange($request->price_min, $request->price_max);
            }

            if ($request->filled('mileage_min') && $request->filled('mileage_max')) {
                $query->byMileageRange($request->mileage_min, $request->mileage_max);
            }

            if ($request->filled('import_type')) {
                $query->where('import_type_id', $request->import_type);
            }

            if ($request->filled('body_type')) {
                $query->where('car_body_type_id', $request->body_type);
            }

            if ($request->filled('fuel_type')) {
                $query->where('fuel_type_id', $request->fuel_type);
            }

            if ($request->filled('cylinders')) {
                $query->where('cylinder_id', $request->cylinders);
            }

            if ($request->filled('transmission')) {
                $query->where('transmission_id', $request->transmission);
            }

            if ($request->filled('exterior_color')) {
                $query->where('exterior_color_id', $request->exterior_color);
            }

            if ($request->filled('warranty')) {
                $query->where('has_warranty', $request->warranty === 'yes');
            }

            if ($request->filled('drive_type')) {
                $query->where('drive_type_id', $request->drive_type);
            }

            // Filter by city_id if provided (city parameter also supported for backward compatibility)
            // If no city_id provided, returns all listings
            if ($request->filled('city_id')) {
                $query->where('city_id', $request->city_id);
            } elseif ($request->filled('city')) {
                // Support 'city' parameter for backward compatibility
                $query->where('city_id', $request->city);
            }

            // Apply sorting
            $sortBy = $request->get('sort', 'latest');
            switch ($sortBy) {
                case 'year_high':
                    $query->orderBy('year', 'desc');
                    break;
                case 'year_low':
                    $query->orderBy('year', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'mileage_high':
                    $query->orderBy('mileage', 'desc');
                    break;
                case 'mileage_low':
                    $query->orderBy('mileage', 'asc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            // Get pagination parameters from request
            $perPage = (int) $request->input('per_page', 7);
            $page = (int) $request->input('page', 1);
            
            // Limit per_page to reasonable range (1-100)
            $perPage = max(1, min(100, $perPage));
            
            $listings = $query->paginate($perPage, ['*'], 'page', $page);

            // Get media counts for all listings in one query (for UI gallery indicator)
            $listingIds = $listings->pluck('id');
            $mediaCounts = \App\Models\Media::where('modelable_type', 'App\Models\CarListing')
                ->whereIn('modelable_id', $listingIds)
                ->selectRaw('modelable_id, COUNT(*) as count')
                ->groupBy('modelable_id')
                ->pluck('count', 'modelable_id');

            // Format listings to return only essential data for UI card
            $formattedListings = $listings->map(function ($listing) use ($mediaCounts) {
                // Get all media with only media_link
                $mediaList = $listing->media->map(function ($media) {
                    if ($media && $media->path_file_name) {
                        return [
                            'media_link' => $media->getS3PublicUrl($media->path_file_name)
                        ];
                    }
                    return null;
                })->filter()->values();

                // Get media count for gallery indicator
                $mediaCount = $mediaCounts->get($listing->id, 0);

                // Get user profile picture
                $profilePictureLink = null;
                if ($listing->user && $listing->user->relationLoaded('profilePictureMedia') && $listing->user->profilePictureMedia) {
                    $profileMedia = $listing->user->profilePictureMedia;
                    if ($profileMedia && $profileMedia->path_file_name) {
                        $profilePictureLink = $profileMedia->getS3PublicUrl($profileMedia->path_file_name);
                    }
                }

                // Build inspection_sides from loaded inspection relationship
                $inspectionSides = null;
                if ($listing->relationLoaded('inspection') && $listing->inspection) {
                    $inspectionSides = [
                        'right_side' => $listing->inspection->right_side ?? false,
                        'left_side' => $listing->inspection->left_side ?? false,
                        'roof' => $listing->inspection->roof ?? false,
                        'front' => $listing->inspection->front ?? false,
                        'back' => $listing->inspection->back ?? false,
                    ];
                }

                return [
                    'id' => $listing->id,
                    'odoo_id' => $listing->odoo_id,
                    'title' => $listing->title,
                    'price' => $listing->price,
                    'price_currency' => $listing->price_currency,
                    'year' => $listing->year,
                    'trim' => $listing->trim,
                    'doors' => $listing->doors,
                    'mileage' => $listing->mileage,
                    'origin_country' => $listing->origin_country,
                    'gcc_import' => $listing->gcc_import,
                    'plate_status' => $listing->plate_status,
                    'condition_type' => $listing->condition_type,
                    'has_warranty' => $listing->has_warranty,
                    'is_inspected' => $listing->is_inspected,
                    'is_verified' => $listing->is_verified,
                    'has_insurance' => $listing->has_insurance,
                    'is_sponsored' => $listing->is_sponsored,
                    'is_featured' => $listing->is_featured,
                    'is_new_arrival' => $listing->is_new_arrival,
                    'city_id' => $listing->city_id,
                    'brand' => $listing->brand ? [
                        'id' => $listing->brand->id,
                        'name' => $listing->brand->name,
                        'odoo_id' => $listing->brand->odoo_id,
                    ] : null,
                    'car_model' => $listing->carModel ? [
                        'id' => $listing->carModel->id,
                        'name' => $listing->carModel->name,
                    ] : null,
                    'body_type' => $listing->bodyType ? [
                        'id' => $listing->bodyType->id,
                        'name' => $listing->bodyType->name,
                    ] : null,
                    'city' => $listing->city ? [
                        'id' => $listing->city->id,
                        'name' => $listing->city->name,
                        'odoo_id' => $listing->city->odoo_id,
                    ] : null,
                    'car_condition' => $listing->carCondition ? [
                        'id' => $listing->carCondition->id,
                        'name' => $listing->carCondition->name,
                    ] : null,
                    'fuel_type' => $listing->fuelType ? [
                        'id' => $listing->fuelType->id,
                        'name' => $listing->fuelType->name,
                    ] : null,
                    'transmission' => $listing->transmission ? [
                        'id' => $listing->transmission->id,
                        'name' => $listing->transmission->name,
                    ] : null,
                    'exterior_color' => $listing->exteriorColor ? [
                        'id' => $listing->exteriorColor->id,
                        'name' => $listing->exteriorColor->name,
                    ] : null,
                    'drive_type' => $listing->driveType ? [
                        'id' => $listing->driveType->id,
                        'name' => $listing->driveType->name,
                    ] : null,
                    'cylinder' => $listing->cylinder ? [
                        'id' => $listing->cylinder->id,
                        'name' => $listing->cylinder->name,
                    ] : null,
                    'media' => $mediaList->toArray(), // All media with only media_link
                    'media_count' => $mediaCount, // For UI gallery indicator (e.g., "1/12")
                    'inspection_sides' => $inspectionSides,
                    'user' => $listing->user ? [
                        'id' => $listing->user->id,
                        'full_name' => $listing->user->full_name,
                        'profile_picture' => $profilePictureLink, // User profile picture
                    ] : null,
                ];
            });

            $data = [
                'listings' => $formattedListings,
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
            ];

            // If brand_id is specified and no listings found, still return brand info
            if ($request->filled('brand_id') && $listings->total() == 0) {
                $brand = \App\Models\CarBrand::with(['media' => function($query) {
                    $query->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                        ->where('info', 'logo');
                }])->find($request->brand_id);
                if ($brand) {
                    $data['brand_info'] = [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'odoo_id' => $brand->odoo_id,
                        'logo_url' => $brand->media ? $brand->media->media_link : null,
                    ];
                }
            }

            // Cache the result for 5 minutes
            Cache::put($cacheKey, $data, 300);

            return $this->successResponse($data, 'Car listings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car listings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get car listing details
     */
    public function getCarListingDetails($id): JsonResponse
    {
        try {
            $listing = CarListing::with([
                'brand', 'carModel', 'carTrim', 'bodyType', 'city', 'studio',
                'media' => function($mediaQuery) {
                    $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder', 'mileageType',
                'user' => function($userQuery) {
                    $userQuery->select('id', 'full_name', 'phone');
                },
                'features', 'specifications', 'inspection'
            ])->findOrFail($id);

            // Load all user media separately since User model uses morphOne
            if ($listing->user) {
                $userMedia = \App\Models\Media::where('modelable_type', 'App\Models\User')
                    ->where('modelable_id', $listing->user->id)
                    ->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                    ->orderBy('order')
                    ->get();
                $listing->user->setRelation('media', $userMedia);
            }

            // Group features by category
            $featuresByCategory = collect();
            try {
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
            } catch (\Exception $e) {
                // Features relationship not properly loaded or is not a collection
                Log::warning('Features relationship issue for listing ' . $listing->id . ': ' . $e->getMessage());
            }

            // Group specifications by type
            $specificationsByType = collect();
            try {
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
            } catch (\Exception $e) {
                // Specifications relationship not properly loaded or is not a collection
                Log::warning('Specifications relationship issue for listing ' . $listing->id . ': ' . $e->getMessage());
            }

            // Format media to return only media_link
            $formattedMedia = $listing->media->map(function ($media) {
                return [
                    'media_link' => $media->media_link ?? $media->getS3TemporaryUrl($media->path_file_name)
                ];
            })->values();

            // Format user to return only full_name, phone, and media
            $formattedUser = null;
            if ($listing->user) {
                $userMediaArray = [];
                if ($listing->user->relationLoaded('media') && $listing->user->media) {
                    $userMediaCollection = $listing->user->media instanceof \Illuminate\Database\Eloquent\Collection 
                        ? $listing->user->media 
                        : collect([$listing->user->media]);
                    
                    $userMediaArray = $userMediaCollection->map(function ($media) {
                        return [
                            'media_link' => $media->media_link ?? $media->getS3TemporaryUrl($media->path_file_name)
                        ];
                    })->values()->toArray();
                }
                
                $formattedUser = [
                    'full_name' => $listing->user->full_name,
                    'phone' => $listing->user->phone,
                    'profile_picture' => $userMediaArray
                ];
            }

            // Format listing object - exclude specified fields
            $formattedListing = [
                'id' => $listing->id,
                'title' => $listing->title,
                'price' => $listing->price,
                'price_currency' => $listing->price_currency,
                'year' => $listing->year,
                'trim' => $listing->trim,
                'doors' => $listing->doors,
                'mileage' => $listing->mileage,
                'origin_country' => $listing->origin_country,
                'gcc_import' => $listing->gcc_import,
                'plate_status' => $listing->plate_status,
                'condition_type' => $listing->condition_type,
                'has_warranty' => $listing->has_warranty,
                'is_inspected' => $listing->is_inspected,
                'is_verified' => $listing->is_verified,
                'has_insurance' => $listing->has_insurance,
                'promotion_currency' => $listing->promotion_currency,
                'is_promoted' => $listing->is_promoted,
                'engine_size' => $listing->engine_size,
                'engine_type' => $listing->engine_type,
                'horsepower' => $listing->horsepower,
                'torque' => $listing->torque,
                'acceleration_0_100' => $listing->acceleration_0_100,
                'top_speed' => $listing->top_speed,
                'seating_capacity' => $listing->seating_capacity,
                'mileage_efficiency' => $listing->mileage_efficiency,
                'length' => $listing->length,
                'width' => $listing->width,
                'height' => $listing->height,
                'status' => $listing->status,
                'status_id' => $listing->status_id,
                'operator_decision' => $listing->operator_decision,
                'is_featured' => $listing->is_featured,
                'is_new_arrival' => $listing->is_new_arrival,
                'description' => $listing->description,
                'features' => $listing->features ?? [],
                'specifications' => $listing->specifications ?? [],
                'sold_at' => $listing->sold_at,
                'featured_at' => $listing->featured_at,
                'body_type' => $listing->bodyType ? [
                    'id' => $listing->bodyType->id,
                    'name' => $listing->bodyType->name,
                ] : null,
                'city' => $listing->city ? [
                    'id' => $listing->city->id,
                    'name' => $listing->city->name,
                ] : null,
                'media' => $formattedMedia,
                'badges' => $listing->badges ?? [],
                'car_condition' => $listing->carCondition ? [
                    'name' => $listing->carCondition->name,
                ] : null,
                'fuel_type' => $listing->fuelType ? [
                    'name' => $listing->fuelType->name,
                ] : null,
                'transmission' => $listing->transmission ? [
                    'name' => $listing->transmission->name,
                ] : null,
                'exterior_color' => $listing->exteriorColor ? [
                    'name' => $listing->exteriorColor->name,
                ] : null,
                'drive_type' => $listing->driveType ? [
                    'name' => $listing->driveType->name,
                ] : null,
                'cylinder' => $listing->cylinder ? [
                    'name' => $listing->cylinder->name,
                ] : null,
                'user' => $formattedUser,
                'mileage_unit' => $listing->mileageType?->name,
                'inspection' => $listing->inspection ? [
                    'inspection_number' => $listing->inspection->inspection_number,
                    'inspection_date' => $listing->inspection->inspection_date,
                    'status' => $listing->inspection->status,
                ] : null,
            ];

            $data = [
                'listing' => $formattedListing,
                'specifications' => [
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
                ],
                'detailed_specifications' => $specificationsByType,
                'features_by_category' => $featuresByCategory,
                'badges' => $listing->active_badges,
                'status_badge' => $listing->status_badge,
                'contact_methods' => \App\Models\ContactMethod::active()->ordered()->get(),
            ];

            return $this->successResponse($data, 'Car listing details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car listing details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get filter options for the frontend
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            $data = [
                'conditions' => \App\Models\CarCondition::all()->makeHidden(['created_at', 'updated_at']),
                'body_types' => \App\Models\BodyType::all()->makeHidden(['created_at', 'updated_at', 'deleted_at', 'description', 'is_active']),
                'fuel_types' => \App\Models\FuelType::all()->makeHidden(['created_at', 'updated_at']),
                'cylinders' => \App\Models\CylinderOption::all()->makeHidden(['created_at', 'updated_at']),
                'transmissions' => \App\Models\Transmission::all()->makeHidden(['created_at', 'updated_at']),
                'exterior_colors' => \App\Models\ExteriorColor::all()->makeHidden(['created_at', 'updated_at']),
                'drive_types' => \App\Models\DriveType::all()->makeHidden(['created_at', 'updated_at']),
                'import_types' => \App\Models\ImportType::all()->makeHidden(['created_at', 'updated_at']),
                'cities' => \App\Models\City::all()->makeHidden(['created_at', 'updated_at', 'deleted_at']),
                'brands' => CarBrand::with('carModels')->get()->map(function ($brand) {
                    $brand->makeHidden(['created_at', 'updated_at', 'deleted_at', 'description', 'is_active', 'logo']);
                    $brand->carModels->makeHidden(['created_at', 'updated_at', 'deleted_at']);
                    return $brand;
                }),
                'status_options' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'sold', 'label' => 'Sold'],
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'expired', 'label' => 'Expired'],
                ],
                'condition_types' => [
                    ['value' => 'new', 'label' => 'New'],
                    ['value' => 'used', 'label' => 'Used'],
                ],
            ];

            return $this->successResponse($data, 'Filter options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve filter options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get car trims for a specific model
     */
    public function getCarTrims($modelId): JsonResponse
    {
        try {
            $trims = CarTrim::where('car_model_id', $modelId)
                ->active()
                ->ordered()
                ->get();

            return $this->successResponse($trims, 'Car trims retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car trims: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get car listings by studio ID
     */
    public function getCarListingsByStudio(Request $request, int $studioId): JsonResponse
    {
        try {
            $cacheKey = 'car_listings_studio_' . $studioId . '_' . md5(serialize($request->all()));

            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                return $this->successResponse($cachedResult, 'Car listings retrieved from cache');
            }

            // Verify studio exists and is active
            $studio = \App\Models\DealerStudio::where('id', $studioId)
                ->where('is_active', true)
                ->first();

            if (!$studio) {
                return $this->errorResponse('Studio not found or inactive', 404);
            }

            // Select only essential fields for listing card UI
            $query = CarListing::select([
                'id', 'odoo_id', 'title', 'price', 'price_currency', 'year', 'trim', 'doors',
                'mileage', 'city_id', 'car_brand_id', 'car_body_type_id', 'car_model_id',
                'car_condition_id', 'fuel_type_id', 'transmission_id', 'exterior_color_id',
                'drive_type_id', 'cylinder_id', 'studio_id', 'is_sponsored', 'is_featured',
                'status', 'status_id', 'operator_decision', 'is_new_arrival', 'description',
                'has_warranty', 'is_inspected', 'is_verified', 'has_insurance',
                'origin_country', 'gcc_import', 'plate_status', 'condition_type',
                'featured_at', 'created_at', 'user_id'
            ])
            ->where('status', 'active') // Filter active only
            ->where('studio_id', $studioId) // Filter by studio ID
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'city:id,name,odoo_id',
                // Load all media for car listing
                'media' => function($mediaQuery) {
                    $mediaQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                // Load inspection sides only (minimal data)
                'inspection' => function($inspectionQuery) {
                    $inspectionQuery->select('id', 'car_listing_id', 'right_side', 'left_side', 'roof', 'front', 'back');
                },
                'carCondition:id,name',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                // User with profile picture
                'user:id,full_name',
                'user.profilePictureMedia' => function($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                        ->where('info', 'profile_picture');
                }
            ]);

            // Apply additional filters if provided
            if ($request->filled('search')) {
                $search = $request->search;
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

            if ($request->filled('brand_id')) {
                $query->where('car_brand_id', $request->brand_id);
            }

            if ($request->filled('city_id')) {
                $cityId = (int) $request->city_id;
                // Validate city exists and filter by city_id
                $city = \App\Models\City::find($cityId);
                if ($city) {
                    $query->where('car_listings.city_id', $cityId);
                } else {
                    // If city doesn't exist, return no results
                    $query->whereRaw('1 = 0');
                }
            }

            if ($request->filled('year_from') && $request->filled('year_to')) {
                $query->byYearRange($request->year_from, $request->year_to);
            }

            if ($request->filled('price_min') && $request->filled('price_max')) {
                $query->byPriceRange($request->price_min, $request->price_max);
            }

            // Apply sorting
            $sortBy = $request->get('sort', 'latest');
            switch ($sortBy) {
                case 'year_high':
                    $query->orderBy('year', 'desc');
                    break;
                case 'year_low':
                    $query->orderBy('year', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'mileage_high':
                    $query->orderBy('mileage', 'desc');
                    break;
                case 'mileage_low':
                    $query->orderBy('mileage', 'asc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            // Get pagination parameters from request
            $perPage = (int) $request->input('per_page', 7);
            $page = (int) $request->input('page', 1);
            
            // Limit per_page to reasonable range (1-100)
            $perPage = max(1, min(100, $perPage));
            
            $listings = $query->paginate($perPage, ['*'], 'page', $page);

            // Get media counts for all listings in one query (for UI gallery indicator)
            $listingIds = $listings->pluck('id');
            $mediaCounts = \App\Models\Media::where('modelable_type', 'App\Models\CarListing')
                ->whereIn('modelable_id', $listingIds)
                ->selectRaw('modelable_id, COUNT(*) as count')
                ->groupBy('modelable_id')
                ->pluck('count', 'modelable_id');

            // Format listings to return only essential data for UI card
            $formattedListings = $listings->map(function ($listing) use ($mediaCounts) {
                // Get all media with only media_link
                $mediaList = $listing->media->map(function ($media) {
                    if ($media && $media->path_file_name) {
                        return [
                            'media_link' => $media->getS3PublicUrl($media->path_file_name)
                        ];
                    }
                    return null;
                })->filter()->values();

                // Get media count for gallery indicator
                $mediaCount = $mediaCounts->get($listing->id, 0);

                // Get user profile picture
                $profilePictureLink = null;
                if ($listing->user && $listing->user->relationLoaded('profilePictureMedia') && $listing->user->profilePictureMedia) {
                    $profileMedia = $listing->user->profilePictureMedia;
                    if ($profileMedia && $profileMedia->path_file_name) {
                        $profilePictureLink = $profileMedia->getS3PublicUrl($profileMedia->path_file_name);
                    }
                }

                // Build inspection_sides from loaded inspection relationship
                $inspectionSides = null;
                if ($listing->relationLoaded('inspection') && $listing->inspection) {
                    $inspectionSides = [
                        'right_side' => $listing->inspection->right_side ?? false,
                        'left_side' => $listing->inspection->left_side ?? false,
                        'roof' => $listing->inspection->roof ?? false,
                        'front' => $listing->inspection->front ?? false,
                        'back' => $listing->inspection->back ?? false,
                    ];
                }

                return [
                    'id' => $listing->id,
                    'odoo_id' => $listing->odoo_id,
                    'title' => $listing->title,
                    'price' => $listing->price,
                    'price_currency' => $listing->price_currency,
                    'year' => $listing->year,
                    'trim' => $listing->trim,
                    'doors' => $listing->doors,
                    'mileage' => $listing->mileage,
                    'origin_country' => $listing->origin_country,
                    'gcc_import' => $listing->gcc_import,
                    'plate_status' => $listing->plate_status,
                    'condition_type' => $listing->condition_type,
                    'has_warranty' => $listing->has_warranty,
                    'is_inspected' => $listing->is_inspected,
                    'is_verified' => $listing->is_verified,
                    'has_insurance' => $listing->has_insurance,
                    'is_sponsored' => $listing->is_sponsored,
                    'is_featured' => $listing->is_featured,
                    'is_new_arrival' => $listing->is_new_arrival,
                    'city_id' => $listing->city_id,
                    'studio_id' => $listing->studio_id,
                    'brand' => $listing->brand ? [
                        'id' => $listing->brand->id,
                        'name' => $listing->brand->name,
                        'odoo_id' => $listing->brand->odoo_id,
                    ] : null,
                    'car_model' => $listing->carModel ? [
                        'id' => $listing->carModel->id,
                        'name' => $listing->carModel->name,
                    ] : null,
                    'body_type' => $listing->bodyType ? [
                        'id' => $listing->bodyType->id,
                        'name' => $listing->bodyType->name,
                    ] : null,
                    'city' => $listing->city ? [
                        'id' => $listing->city->id,
                        'name' => $listing->city->name,
                        'odoo_id' => $listing->city->odoo_id,
                    ] : null,
                    'car_condition' => $listing->carCondition ? [
                        'id' => $listing->carCondition->id,
                        'name' => $listing->carCondition->name,
                    ] : null,
                    'fuel_type' => $listing->fuelType ? [
                        'id' => $listing->fuelType->id,
                        'name' => $listing->fuelType->name,
                    ] : null,
                    'transmission' => $listing->transmission ? [
                        'id' => $listing->transmission->id,
                        'name' => $listing->transmission->name,
                    ] : null,
                    'exterior_color' => $listing->exteriorColor ? [
                        'id' => $listing->exteriorColor->id,
                        'name' => $listing->exteriorColor->name,
                    ] : null,
                    'drive_type' => $listing->driveType ? [
                        'id' => $listing->driveType->id,
                        'name' => $listing->driveType->name,
                    ] : null,
                    'cylinder' => $listing->cylinder ? [
                        'id' => $listing->cylinder->id,
                        'name' => $listing->cylinder->name,
                    ] : null,
                    'media' => $mediaList->toArray(), // All media with only media_link
                    'media_count' => $mediaCount, // For UI gallery indicator (e.g., "1/12")
                    'inspection_sides' => $inspectionSides,
                    'user' => $listing->user ? [
                        'id' => $listing->user->id,
                        'full_name' => $listing->user->full_name,
                        'profile_picture' => $profilePictureLink, // User profile picture
                    ] : null,
                ];
            });

            $data = [
                'listings' => $formattedListings,
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'studio_id' => $studioId,
                'studio' => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'city_id' => $studio->city_id,
                ],
            ];

            // Cache the result for 5 minutes
            Cache::put($cacheKey, $data, 300);

            return $this->successResponse($data, 'Car listings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car listings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get news articles
     */
    public function getNewsArticles(Request $request): JsonResponse
    {
        try {
            $query = NewsArticle::published();

            if ($request->filled('category')) {
                $query->byCategory($request->category);
            }

            if ($request->filled('featured')) {
                $query->featured();
            }

            $articles = $query->latest(10)->get();

            return $this->successResponse($articles, 'News articles retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve news articles: ' . $e->getMessage(), 500);
        }
    }
}
