<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class CarListingRecommendationService
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Get recommended car listings for a user based on their latest search history.
     *
     * @param  User|null  $user
     * @param  int  $limit
     * @return array{listings: \Illuminate\Support\Collection, meta: array<string, mixed>}
     */
    public function getRecommendations(?User $user, int $limit = 2): array
    {
        $limit = $this->normalizeLimit($limit);

        $recommendations = collect();
        $primarySource = 'top_picks';
        $searchHistoryIds = [];

        if ($user) {
            $recentSearches = $this->getRecentSearchHistories($user, 2);

            foreach ($recentSearches as $searchHistory) {
                $searchHistoryIds[] = $searchHistory->id;

                $remaining = $limit - $recommendations->count();
                if ($remaining <= 0) {
                    break;
                }

                $searchResults = $this->getListingsFromSearchHistory($searchHistory, $remaining);
                $listings = $searchResults['listings'];

                if ($listings->isNotEmpty() && $primarySource !== 'search_history') {
                    $primarySource = 'search_history';
                }

                $recommendations = $recommendations->merge($listings);
            }
        }

        $recommendations = $this->mergeWithFallbackListings($recommendations, $limit, $primarySource);

        return [
            'listings' => $recommendations->unique('id')->take($limit)->values(),
            'meta' => [
                'limit' => $limit,
                'source' => $recommendations->isEmpty() ? 'top_picks' : $primarySource,
                'search_history_ids' => $searchHistoryIds,
            ],
        ];
    }

    private function normalizeLimit(int $limit): int
    {
        return max(1, min($limit, 10));
    }

    /**
     * @return \Illuminate\Support\Collection<int, SearchHistory>
     */
    private function getRecentSearchHistories(User $user, int $limit): Collection
    {
        return SearchHistory::where('user_id', $user->id)
            ->orderByRaw('COALESCE(last_searched_at, updated_at, created_at) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{listings: Collection}
     */
    private function getListingsFromSearchHistory(SearchHistory $searchHistory, int $limit): array
    {
        $filters = $searchHistory->filters ?? [];

        if (!isset($filters['search']) && !empty($searchHistory->search_query)) {
            $filters['search'] = $searchHistory->search_query;
        }

        $filters = $this->cleanFilters($filters);

        $searchResults = $this->searchService->searchListings($filters, 1, $limit);

        return [
            'listings' => collect($searchResults['listings'] ?? []),
        ];
    }

    /**
     * Merge current recommendations with fallback sponsored listings if needed.
     *
     * @param  Collection  $current
     * @param  int  $limit
     * @param  string  $primarySource
     * @return Collection
     */
    private function mergeWithFallbackListings(Collection &$current, int $limit, string &$primarySource): Collection
    {
        $neededCount = $limit - $current->count();

        if ($neededCount <= 0) {
            return $current;
        }

        $fallback = $this->getSponsoredFallbackListings(
            $current->pluck('id')->all(),
            $neededCount
        );

        if ($fallback->isNotEmpty() && $primarySource !== 'search_history') {
            $primarySource = 'top_picks';
        }

        return $current->merge($fallback);
    }

    /**
     * Fetch fallback sponsored listings.
     */
    private function getSponsoredFallbackListings(array $excludeIds, int $limit): EloquentCollection
    {
        // Optimize query with limited column selection and specific media loading
        return CarListing::select('car_listings.*')
            ->with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'bodyType:id,name',
                'city:id,name,odoo_id',
                'studio:id,name,city_id',
                'media' => function ($query) {
                    $query->select('id', 'modelable_type', 'modelable_id', 'path_file_name', 'file_type', 'info', 'order')
                        ->orderBy('order');
                },
                'badges:id,name',
                'carCondition:id,name',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                'user:id,full_name'
            ])
            ->where('is_promoted', true)
            ->active()
            ->whereNull('sold_at')
            ->when(!empty($excludeIds), function ($query) use ($excludeIds) {
                $query->whereNotIn('id', $excludeIds);
            })
            ->orderByDesc('promotion_started_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Remove empty filter values to avoid broad queries.
     */
    private function cleanFilters(array $filters): array
    {
        return array_filter($filters, function ($value) {
            if (is_array($value)) {
                $filtered = array_filter($value, fn ($inner) => $inner !== null && $inner !== '');
                return !empty($filtered);
            }

            return $value !== null && $value !== '';
        });
    }
}

