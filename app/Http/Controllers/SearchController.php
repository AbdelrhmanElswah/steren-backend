<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Requests\BrandSearchRequest;
use App\Http\Requests\SaveSearchHistoryRequest;
use App\Http\Resources\SearchResultResource;
use App\Http\Resources\BrandSearchResource;
use App\Http\Resources\ModelSearchResource;
use App\Http\Resources\SearchSuggestionsResource;
use App\Http\Resources\SponsoredListingResource;
use App\Services\SearchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use ApiResponse;

    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search for car brands and models
     */
    public function searchBrandsAndModels(BrandSearchRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $results = $this->searchService->searchBrandsAndModels(
                $validated['search'],
                $validated['limit'] ?? 10
            );

            $data = [
                'brands' => BrandSearchResource::collection($results['brands']),
                'models' => ModelSearchResource::collection($results['models']),
                'meta' => [
                    'total_brands' => $results['total_brands'],
                    'total_models' => $results['total_models'],
                    'search_term' => $validated['search'],
                ]
            ];

            return $this->successResponse($data, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search brands and models: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get search suggestions for a specific brand
     */
    public function getBrandSuggestions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'brand' => 'required|string|max:255'
            ]);

            $results = $this->searchService->getBrandSearchSuggestions($request->brand);
            
            if (!$results['brand']) {
                return $this->errorResponse('Brand not found', 404);
            }

            $data = new SearchSuggestionsResource($results);

            return $this->successResponse($data, 'Brand suggestions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get brand suggestions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search car listings with filters
     */
    public function searchListings(SearchRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 20;
            $lang = $request->query('lang');
            if (!in_array($lang, ['ar', 'kr'])) {
                $lang = null;
            }

            $results = $this->searchService->searchListings($validated, $page, $perPage, $lang);

            $data = [
                'listings' => $results['listings'], // Already formatted by SearchService
                'pagination' => [
                    'current_page' => $results['current_page'],
                    'per_page' => $results['per_page'],
                    'last_page' => $results['last_page'],
                    'has_more_pages' => $results['has_more_pages'],
                    'total' => $results['total'],
                ],
                'filters_applied' => array_filter($validated, function($value, $key) {
                    return !in_array($key, ['page', 'per_page', 'sort']) && $value !== null;
                }, ARRAY_FILTER_USE_BOTH)
            ];

            return $this->successResponse($data, 'Car listings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search listings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sponsored listings for "You May Also Like" section
     */
    public function getSponsoredListings(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:10'
            ]);

            $listings = $this->searchService->getSponsoredListings($request->get('limit', 2));
            $data = SponsoredListingResource::collection($listings);

            return $this->successResponse($data, 'Sponsored listings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get sponsored listings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all car brands with counts
     */
    public function getAllBrands(Request $request): JsonResponse
    {
        try {
            $brands = $this->searchService->getAllBrandsWithCounts();
            $data = BrandSearchResource::collection($brands);

            return $this->successResponse($data, 'Car brands retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get car brands: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get filter options for search
     */
    public function getFilterOptions(Request $request): JsonResponse
    {
        try {
            $lang = $request->query('lang');
            if (!in_array($lang, ['ar', 'kr'])) {
                $lang = null;
            }
            
            $options = $this->searchService->getFilterOptions($lang);

            return $this->successResponse($options, 'Filter options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get filter options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get recent searches for authenticated user
     */
    public function getRecentSearches(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            
            if (!$userId) {
                return $this->errorResponse('User authentication required', 401);
            }

            $request->validate([
                'limit' => 'nullable|integer|min:1|max:10'
            ]);

            $searches = $this->searchService->getRecentSearches($userId, $request->get('limit', 5));

            return $this->successResponse([
                'recent_searches' => $searches
            ], 'Recent searches retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get recent searches: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Save search term to user's history
     */
    public function saveSearchHistory(SaveSearchHistoryRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            
            if (!$userId) {
                return $this->errorResponse('User authentication required', 401);
            }

            $validated = $request->validated();
            $this->searchService->saveSearchTerm($userId, $validated['search_term']);

            return $this->successResponse([], 'Search term saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save search term: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a specific search history entry
     */
    public function deleteRecentSearch(Request $request, int $searchId): JsonResponse
    {
        try {
            $userId = $request->user()?->id;

            if (!$userId) {
                return $this->errorResponse('User authentication required', 401);
            }

            $deleted = $this->searchService->deleteSearchHistory($userId, $searchId);

            if (!$deleted) {
                return $this->errorResponse('Search history entry not found', 404);
            }

            return $this->successResponse([], 'Search history entry deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete search history entry: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clear search cache (Admin only)
     */
    public function clearSearchCache(Request $request): JsonResponse
    {
        try {
            // Add authorization check for admin users if needed
            $this->searchService->clearSearchCache();

            return $this->successResponse([], 'Search cache cleared successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to clear search cache: ' . $e->getMessage(), 500);
        }
    }
}
