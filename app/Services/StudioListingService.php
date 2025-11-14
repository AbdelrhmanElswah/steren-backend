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
                    'listings' => $this->formatListings($newListings),
                ],
                'used' => [
                    'count' => $usedListings->count(),
                    'listings' => $this->formatListings($usedListings),
                ],
            ];
        });
    }

    private function buildBaseQuery(int $studioId)
    {
        // Optimize query with limited column selection and specific media loading
        return CarListing::select('car_listings.*')
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'location:id,name',
                'carCondition:id,name',
                'media' => function ($query) {
                    $query->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->where('info', 'primary')
                        ->orderBy('order')
                        ->limit(1);
                },
                'user:id,full_name',
            ])
            ->where('studio_id', $studioId)
            ->where('status', 'active')
            ->whereNull('sold_at');
    }
    
    /**
     * Format listings directly without using resources
     */
    private function formatListings($listings): array
    {
        return $listings->map(function ($listing) {
            $mediaLink = null;
            $media = $listing->media?->first();
            if ($media && $media->path_file_name) {
                // Use public URL (already optimized, no S3 API calls)
                $mediaLink = $media->getS3PublicUrl($media->path_file_name);
            }

            return [
                'id' => $listing->id,
                'odoo_id' => $listing->odoo_id,
                'title' => $listing->title,
                'price' => $listing->price,
                'year' => $listing->year,
                'mileage' => $listing->mileage,
                'brand' => $listing->brand ? ['id' => $listing->brand->id, 'name' => $listing->brand->name] : null,
                'car_model' => $listing->carModel ? ['id' => $listing->carModel->id, 'name' => $listing->carModel->name] : null,
                'location' => $listing->location ? ['id' => $listing->location->id, 'name' => $listing->location->name] : null,
                'car_condition' => $listing->carCondition ? ['id' => $listing->carCondition->id, 'name' => $listing->carCondition->name] : null,
                'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                'created_at' => $listing->created_at?->toISOString(),
            ];
        })->toArray();
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


