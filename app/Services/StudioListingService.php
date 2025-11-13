<?php

namespace App\Services;

use App\Http\Resources\Api\CarListingResource;
use App\Models\CarListing;
use App\Models\DealerStudio;
use Illuminate\Database\Eloquent\Collection;

class StudioListingService
{
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
        $studio = DealerStudio::findOrFail($studioId);

        $baseQuery = $this->buildBaseQuery($studioId);

        $newListings = $this->getNewListings(clone $baseQuery);
        $usedListings = $this->getUsedListings(clone $baseQuery, $newListings->pluck('id')->all());

        return [
            'studio' => [
                'id' => $studio->id,
                'name' => $studio->name,
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

    private function buildBaseQuery(int $studioId)
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
        ->where('studio_id', $studioId)
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


