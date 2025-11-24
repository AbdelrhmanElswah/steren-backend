<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\DealerStudio;
use Illuminate\Database\Eloquent\Collection;

class StudioListingService
{
    protected CarListingFormatterService $formatter;

    public function __construct(CarListingFormatterService $formatter)
    {
        $this->formatter = $formatter;
    }
    /**
     * Get listings for a studio grouped by condition.
     *
     * @param  int  $studioId
     * @return array{studio: array<string, mixed>, new: array<string, mixed>, used: array<string, mixed>}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getListingsGroupedByCondition(int $studioId): array
    {
        // Build cache key
        $cacheKey = 'studio_condition_groups_' . $studioId;
        
        // Cache for 24 hours (86400 seconds)
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($studioId) {
            $studio = DealerStudio::select('id', 'name')->findOrFail($studioId);

            $baseQuery = $this->buildBaseQuery($studioId);

            $newListings = $this->getNewListings(clone $baseQuery);
            $usedListings = $this->getUsedListings(clone $baseQuery, $newListings->pluck('id')->all());

            // Build response directly without using resources (faster)
            return [
                'studio' => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                ],
                'new' => [
                    'count' => $newListings->count(),
                    'listings' => $this->formatListings($newListings), // Use standardized format for consistency
                ],
                'used' => [
                    'count' => $usedListings->count(),
                    'listings' => $this->formatListings($usedListings), // Use standardized format for used cars
                ],
            ];
        });
    }

    private function buildBaseQuery(int $studioId)
    {
        // Optimize query with limited column selection and specific media loading
        // Load all relationships needed for standardized format
        return CarListing::select('car_listings.*')
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'city:id,name,odoo_id',
                'carCondition:id,name',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                'mileageType:id,name',
                'media' => function ($query) {
                    $query->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                'inspection' => function($inspectionQuery) {
                    $inspectionQuery->select('id', 'car_listing_id', 'right_side', 'left_side', 'roof', 'front', 'back');
                },
                'user:id,full_name',
                'user.profilePictureMedia' => function($profileQuery) {
                    $profileQuery->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info')
                        ->where('info', 'profile_picture');
                }
            ])
            ->where('studio_id', $studioId)
            ->where('status', 'active')
            ->whereNull('sold_at');
    }
    
    /**
     * Format listings using shared formatter to match /api/car-listings format
     */
    private function formatListings($listings): array
    {
        return $this->formatter->formatListings($listings);
    }

    private function getNewListings($query): Collection
    {
        return $query
            ->where(function ($conditionQuery) {
                $conditionQuery->whereRaw('LOWER(condition_type) = ?', ['new'])
                    ->orWhereHas('carCondition', function ($carConditionQuery) {
                        $carConditionQuery->whereRaw('LOWER(name) = ?', ['new']);
                    });
            })
            ->orderByDesc('created_at')
            ->get();
    }

    private function getUsedListings($query, array $excludeIds): Collection
    {
        return $query
            ->when(!empty($excludeIds), function ($usedQuery) use ($excludeIds) {
                $usedQuery->whereNotIn('id', $excludeIds);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get studio listings with custom structure for FE needs
     * Used listings: same format as condition-groups
     * New listings: grouped by model with trims
     *
     * @param  int  $studioId
     * @return array{studio: array<string, mixed>, new: array<string, mixed>, used: array<string, mixed>}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getStudioListingsCustomStructure(int $studioId): array
    {
        // Build cache key
        $cacheKey = 'studio_custom_structure_' . $studioId;
        
        // Cache for 24 hours (86400 seconds)
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($studioId) {
            $studio = DealerStudio::select('id', 'name')->findOrFail($studioId);

            $baseQuery = $this->buildBaseQuery($studioId);

            $newListings = $this->getNewListings(clone $baseQuery);
            $usedListings = $this->getUsedListings(clone $baseQuery, $newListings->pluck('id')->all());

            // Format used listings (same as condition-groups)
            $usedFormatted = $this->formatListings($usedListings);

            // Format new listings (custom structure by model)
            $newFormatted = $this->formatNewListingsByModel($newListings);

            return [
                'studio' => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                ],
                'new' => [
                    'count' => $newListings->count(),
                    'models' => $newFormatted,
                ],
                'used' => [
                    'count' => $usedListings->count(),
                    'listings' => $usedFormatted,
                ],
            ];
        });
    }

    /**
     * Format new listings grouped by model with custom structure
     */
    private function formatNewListingsByModel(Collection $listings): array
    {
        if ($listings->isEmpty()) {
            return [];
        }

        // Get exchange rate for IQD to USD conversion (for starting_from only)
        $iqdPerUsd = $this->getIqdPerUsdRate();

        // Group listings by model
        $groupedByModel = $listings->groupBy('car_model_id');

        $models = [];

        foreach ($groupedByModel as $modelId => $modelListings) {
            $firstListing = $modelListings->first();
            $model = $firstListing->carModel;

            if (!$model) {
                continue;
            }

            // Get first image from oldest listing (order by created_at ASC, then get first media)
            $oldestListing = $modelListings->sortBy('created_at')->first();
            $image = null;
            if ($oldestListing && $oldestListing->relationLoaded('media') && $oldestListing->media->isNotEmpty()) {
                $firstMedia = $oldestListing->media->sortBy('order')->first();
                if ($firstMedia) {
                    // Use public_url accessor which generates S3 public URL
                    $image = $firstMedia->public_url ?? $firstMedia->media_temporary_url ?? $firstMedia->path_file_name;
                }
            }

            // Build trims array from listings (keep original price and currency)
            $trims = $modelListings->map(function ($listing) {
                return [
                    'listing_id' => $listing->id,
                    'name' => $listing->trim ?? 'Base', // Use trim field from listing, fallback to 'Base'
                    'engine_size' => $listing->engine_size ?? null,
                    'price' => (string) $listing->price, // Keep original price (not converted)
                    'currency' => $listing->price_currency ?? 'USD', // Include currency
                ];
            })->values()->toArray();

            // Calculate starting_from: convert all prices to USD, find minimum, return in USD
            $minPriceUsd = $modelListings->map(function ($listing) use ($iqdPerUsd) {
                return $this->convertPriceToUsd($listing->price, $listing->price_currency, $iqdPerUsd);
            })->min();

            $models[] = [
                'name' => $model->name,
                'starting_from' => (string) $minPriceUsd, // Minimum price in USD
                'image' => $image,
                'trims' => $trims,
            ];
        }

        return $models;
    }

    /**
     * Convert price to USD
     * 
     * @param float $price
     * @param string|null $currency
     * @param float $iqdPerUsd
     * @return float
     */
    private function convertPriceToUsd(float $price, ?string $currency, float $iqdPerUsd): float
    {
        // If currency is IQD, convert to USD
        if ($currency === 'IQD') {
            return $price / $iqdPerUsd;
        }
        
        // If currency is USD or null, return as is
        return $price;
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


