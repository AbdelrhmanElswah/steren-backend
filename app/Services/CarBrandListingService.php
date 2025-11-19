<?php

namespace App\Services;

use App\Http\Resources\Api\CarListingResource;
use App\Models\CarBrand;
use App\Models\CarListing;
use Illuminate\Database\Eloquent\Collection;

class CarBrandListingService
{
    protected CarListingFormatterService $formatter;

    public function __construct(CarListingFormatterService $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Get listings for a brand grouped by condition.
     *
     * @param  int  $brandId
     * @return array{brand: array<string, mixed>, new: array<string, mixed>, used: array<string, mixed>}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getListingsGroupedByCondition(int $brandId): array
    {
        $carBrand = CarBrand::findOrFail($brandId);

        $baseQuery = $this->buildBaseQuery($brandId);

        $newListings = $this->getNewListings(clone $baseQuery);
        $usedListings = $this->getUsedListings(clone $baseQuery, $newListings->pluck('id')->all());

        return [
            'brand' => [
                'id' => $carBrand->id,
                'name' => $carBrand->name,
            ],
            'new' => [
                'count' => $newListings->count(),
                'listings' => CarListingResource::collection($newListings)->collection, // Keep old format for new cars
            ],
            'used' => [
                'count' => $usedListings->count(),
                'listings' => $this->formatter->formatListings($usedListings), // Use new standardized format for used cars
            ],
        ];
    }

    private function buildBaseQuery(int $brandId)
    {
        // Load all relationships needed for standardized format
        return CarListing::with([
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
        ->where('car_brand_id', $brandId)
        ->where('status', 'active')
        ->whereNull('sold_at');
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

