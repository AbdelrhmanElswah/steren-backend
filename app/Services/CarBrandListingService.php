<?php

namespace App\Services;

use App\Http\Resources\Api\CarListingResource;
use App\Models\CarBrand;
use App\Models\CarListing;
use Illuminate\Database\Eloquent\Collection;

class CarBrandListingService
{
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
                'listings' => CarListingResource::collection($newListings)->collection,
            ],
            'used' => [
                'count' => $usedListings->count(),
                'listings' => CarListingResource::collection($usedListings)->collection,
            ],
        ];
    }

    private function buildBaseQuery(int $brandId)
    {
        return CarListing::with([
            'brand',
            'carModel',
            'location',
            'carCondition',
            'media' => function ($query) {
                $query->where('info', 'primary')
                    ->orderBy('order')
                    ->orderBy('created_at');
            },
            'user:id,full_name',
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

