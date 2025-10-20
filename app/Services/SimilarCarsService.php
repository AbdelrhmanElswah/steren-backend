<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\CarBrand;
use App\Models\CarModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SimilarCarsService
{
    /**
     * Get similar cars based on a reference car listing
     */
    public function getSimilarCars(int $carListingId, int $limit = 8): array
    {
        $cacheKey = "similar_cars_{$carListingId}_{$limit}";
        
        return Cache::remember($cacheKey, 900, function () use ($carListingId, $limit) {
            $referenceCar = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder'
            ])->find($carListingId);

            if (!$referenceCar) {
                return [
                    'similar_cars' => collect(),
                    'total' => 0,
                    'reference_car' => null,
                    'similarity_factors' => []
                ];
            }

            $similarCars = $this->findSimilarCars($referenceCar, $limit);
            
            return [
                'similar_cars' => $similarCars,
                'total' => $similarCars->count(),
                'reference_car' => $referenceCar,
                'similarity_factors' => $this->getSimilarityFactors($referenceCar)
            ];
        });
    }

    /**
     * Get similar cars by brand and model
     */
    public function getSimilarCarsByBrandModel(int $brandId, int $modelId = null, int $limit = 8): array
    {
        $cacheKey = "similar_cars_brand_{$brandId}_model_{$modelId}_{$limit}";
        
        return Cache::remember($cacheKey, 900, function () use ($brandId, $modelId, $limit) {
            $query = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%');
                }
            ])
            ->where('car_brand_id', $brandId)
            ->where('status', 'active')
            ->whereNull('sold_at');

            if ($modelId) {
                $query->where('car_model_id', $modelId);
            }

            $similarCars = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'similar_cars' => $similarCars,
                'total' => $similarCars->count(),
                'brand' => CarBrand::find($brandId),
                'model' => $modelId ? CarModel::find($modelId) : null
            ];
        });
    }

    /**
     * Get similar cars by price range
     */
    public function getSimilarCarsByPrice(float $price, float $priceTolerance = 0.2, int $limit = 8): array
    {
        $cacheKey = "similar_cars_price_{$price}_{$priceTolerance}_{$limit}";
        
        return Cache::remember($cacheKey, 600, function () use ($price, $priceTolerance, $limit) {
            $minPrice = $price * (1 - $priceTolerance);
            $maxPrice = $price * (1 + $priceTolerance);

            $similarCars = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%');
                }
            ])
            ->whereBetween('price', [$minPrice, $maxPrice])
            ->where('status', 'active')
            ->whereNull('sold_at')
            ->orderByRaw('ABS(price - ?)', [$price])
            ->limit($limit)
            ->get();

            return [
                'similar_cars' => $similarCars,
                'total' => $similarCars->count(),
                'price_range' => [
                    'min' => $minPrice,
                    'max' => $maxPrice,
                    'reference' => $price
                ]
            ];
        });
    }

    /**
     * Get similar cars by year range
     */
    public function getSimilarCarsByYear(int $year, int $yearTolerance = 2, int $limit = 8): array
    {
        $cacheKey = "similar_cars_year_{$year}_{$yearTolerance}_{$limit}";
        
        return Cache::remember($cacheKey, 600, function () use ($year, $yearTolerance, $limit) {
            $minYear = $year - $yearTolerance;
            $maxYear = $year + $yearTolerance;

            $similarCars = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%');
                }
            ])
            ->whereBetween('year', [$minYear, $maxYear])
            ->where('status', 'active')
            ->whereNull('sold_at')
            ->orderByRaw('ABS(year - ?)', [$year])
            ->limit($limit)
            ->get();

            return [
                'similar_cars' => $similarCars,
                'total' => $similarCars->count(),
                'year_range' => [
                    'min' => $minYear,
                    'max' => $maxYear,
                    'reference' => $year
                ]
            ];
        });
    }

    /**
     * Get similar cars by body type
     */
    public function getSimilarCarsByBodyType(int $bodyTypeId, int $limit = 8): array
    {
        $cacheKey = "similar_cars_body_type_{$bodyTypeId}_{$limit}";
        
        return Cache::remember($cacheKey, 900, function () use ($bodyTypeId, $limit) {
            $similarCars = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%');
                }
            ])
            ->where('car_body_type_id', $bodyTypeId)
            ->where('status', 'active')
            ->whereNull('sold_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

            return [
                'similar_cars' => $similarCars,
                'total' => $similarCars->count(),
                'body_type' => \App\Models\BodyType::find($bodyTypeId)
            ];
        });
    }

    /**
     * Get similar cars by multiple criteria (advanced)
     */
    public function getSimilarCarsAdvanced(array $criteria, int $limit = 8): array
    {
        $cacheKey = 'similar_cars_advanced_' . md5(serialize($criteria)) . "_{$limit}";
        
        return Cache::remember($cacheKey, 600, function () use ($criteria, $limit) {
            $query = CarListing::with([
                'brand',
                'carModel',
                'bodyType',
                'fuelType',
                'transmission',
                'exteriorColor',
                'driveType',
                'cylinder',
                'media' => function ($query) {
                    $query->where('file_type', 'like', 'image/%');
                }
            ]);

            // Apply criteria filters
            if (!empty($criteria['brand_id'])) {
                $query->where('car_brand_id', $criteria['brand_id']);
            }

            if (!empty($criteria['model_id'])) {
                $query->where('car_model_id', $criteria['model_id']);
            }

            if (!empty($criteria['body_type_id'])) {
                $query->where('car_body_type_id', $criteria['body_type_id']);
            }

            if (!empty($criteria['fuel_type_id'])) {
                $query->where('fuel_type_id', $criteria['fuel_type_id']);
            }

            if (!empty($criteria['transmission_id'])) {
                $query->where('transmission_id', $criteria['transmission_id']);
            }

            if (!empty($criteria['drive_type_id'])) {
                $query->where('drive_type_id', $criteria['drive_type_id']);
            }

            if (!empty($criteria['year_min']) && !empty($criteria['year_max'])) {
                $query->whereBetween('year', [$criteria['year_min'], $criteria['year_max']]);
            }

            if (!empty($criteria['price_min']) && !empty($criteria['price_max'])) {
                $query->whereBetween('price', [$criteria['price_min'], $criteria['price_max']]);
            }

            if (!empty($criteria['mileage_min']) && !empty($criteria['mileage_max'])) {
                $query->whereBetween('mileage', [$criteria['mileage_min'], $criteria['mileage_max']]);
            }

            // Exclude specific car if provided
            if (!empty($criteria['exclude_id'])) {
                $query->where('id', '!=', $criteria['exclude_id']);
            }

            $similarCars = $query->where('status', 'active')
                ->whereNull('sold_at')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'similar_cars' => $similarCars,
                'total' => $similarCars->count(),
                'criteria' => $criteria
            ];
        });
    }

    /**
     * Find similar cars based on reference car
     */
    private function findSimilarCars(CarListing $referenceCar, int $limit): \Illuminate\Database\Eloquent\Collection
    {
        // Build similarity query with weighted scoring
        $query = CarListing::with([
            'brand',
            'carModel',
            'bodyType',
            'fuelType',
            'transmission',
            'exteriorColor',
            'driveType',
            'cylinder',
            'media' => function ($query) {
                $query->where('file_type', 'like', 'image/%')->orderBy('order');
            }
        ])
        ->where('status', 'active')
        ->whereNull('sold_at')
        ->where('id', '!=', $referenceCar->id);

        // Apply similarity filters with weights
        $similarityConditions = [];

        // Same brand (high weight)
        if ($referenceCar->car_brand_id) {
            $similarityConditions[] = "CASE WHEN car_brand_id = {$referenceCar->car_brand_id} THEN 10 ELSE 0 END";
        }

        // Same model (high weight)
        if ($referenceCar->car_model_id) {
            $similarityConditions[] = "CASE WHEN car_model_id = {$referenceCar->car_model_id} THEN 15 ELSE 0 END";
        }

        // Same body type (medium weight)
        if ($referenceCar->car_body_type_id) {
            $similarityConditions[] = "CASE WHEN car_body_type_id = {$referenceCar->car_body_type_id} THEN 8 ELSE 0 END";
        }

        // Same fuel type (medium weight)
        if ($referenceCar->fuel_type_id) {
            $similarityConditions[] = "CASE WHEN fuel_type_id = {$referenceCar->fuel_type_id} THEN 6 ELSE 0 END";
        }

        // Same transmission (low weight)
        if ($referenceCar->transmission_id) {
            $similarityConditions[] = "CASE WHEN transmission_id = {$referenceCar->transmission_id} THEN 4 ELSE 0 END";
        }

        // Same drive type (low weight)
        if ($referenceCar->drive_type_id) {
            $similarityConditions[] = "CASE WHEN drive_type_id = {$referenceCar->drive_type_id} THEN 3 ELSE 0 END";
        }

        // Price similarity (medium weight)
        if ($referenceCar->price) {
            $priceTolerance = $referenceCar->price * 0.3; // 30% tolerance
            $similarityConditions[] = "CASE WHEN ABS(price - {$referenceCar->price}) <= {$priceTolerance} THEN 5 ELSE 0 END";
        }

        // Year similarity (medium weight)
        if ($referenceCar->year) {
            $yearTolerance = 2;
            $similarityConditions[] = "CASE WHEN ABS(year - {$referenceCar->year}) <= {$yearTolerance} THEN 7 ELSE 0 END";
        }

        // Mileage similarity (low weight)
        if ($referenceCar->mileage) {
            $mileageTolerance = $referenceCar->mileage * 0.5; // 50% tolerance
            $similarityConditions[] = "CASE WHEN ABS(mileage - {$referenceCar->mileage}) <= {$mileageTolerance} THEN 2 ELSE 0 END";
        }

        if (!empty($similarityConditions)) {
            $similarityScore = implode(' + ', $similarityConditions);
            $query->selectRaw("*, ({$similarityScore}) as similarity_score")
                  ->orderBy('similarity_score', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get similarity factors for reference car
     */
    private function getSimilarityFactors(CarListing $referenceCar): array
    {
        return [
            'brand' => $referenceCar->brand?->name,
            'model' => $referenceCar->carModel?->name,
            'body_type' => $referenceCar->bodyType?->name,
            'fuel_type' => $referenceCar->fuelType?->name,
            'transmission' => $referenceCar->transmission?->name,
            'drive_type' => $referenceCar->driveType?->name,
            'price_range' => [
                'min' => $referenceCar->price * 0.7,
                'max' => $referenceCar->price * 1.3,
                'reference' => $referenceCar->price
            ],
            'year_range' => [
                'min' => $referenceCar->year - 2,
                'max' => $referenceCar->year + 2,
                'reference' => $referenceCar->year
            ]
        ];
    }

    /**
     * Clear similar cars cache
     */
    public function clearSimilarCarsCache(): void
    {
        $patterns = [
            'similar_cars_*',
            'similar_cars_brand_*',
            'similar_cars_price_*',
            'similar_cars_year_*',
            'similar_cars_body_type_*',
            'similar_cars_advanced_*'
        ];

        foreach ($patterns as $pattern) {
            Cache::flush();
        }
    }
}
