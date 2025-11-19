<?php

namespace App\Services;

use App\Http\Resources\Api\CarListingResource;
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
                    'listings' => CarListingResource::collection($newListings)->collection, // Keep old format for new cars
                ],
                'used' => [
                    'count' => $usedListings->count(),
                    'listings' => $this->formatListings($usedListings), // Use new standardized format for used cars
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
}


