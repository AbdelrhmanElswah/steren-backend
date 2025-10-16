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

class CarListingController extends Controller
{
    use ApiResponse;

    /**
     * Get car listings with filters and sorting
     */
    public function getCarListings(Request $request): JsonResponse
    {
        try {
            $query = CarListing::with([
                'brand', 'carModel', 'bodyType', 'location', 'studio',
                'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder'
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

            if ($request->filled('city')) {
                $query->whereHas('location', function($q) use ($request) {
                    $q->where('city_id', $request->city);
                });
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

            $listings = $query->active()->paginate(20);

            $data = [
                'listings' => $listings->items(),
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
            ];

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
                'brand', 'carModel', 'carTrim', 'bodyType', 'location', 'studio',
                'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder', 'user',
                'features', 'specifications'
            ])->findOrFail($id);

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

            $data = [
                'listing' => $listing,
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
                'conditions' => \App\Models\CarCondition::all(),
                'body_types' => \App\Models\BodyType::all(),
                'fuel_types' => \App\Models\FuelType::all(),
                'cylinders' => \App\Models\CylinderOption::all(),
                'transmissions' => \App\Models\Transmission::all(),
                'exterior_colors' => \App\Models\ExteriorColor::all(),
                'drive_types' => \App\Models\DriveType::all(),
                'import_types' => \App\Models\ImportType::all(),
                'cities' => \App\Models\City::with('country')->get(),
                'brands' => CarBrand::with('carModels')->get(),
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
