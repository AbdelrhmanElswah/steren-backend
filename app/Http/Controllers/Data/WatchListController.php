<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\SearchHistory;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WatchListController extends Controller
{
    use ApiResponse;

    /**
     * Get user's watch list (favorites)
     */
    public function getWatchList(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $query = Favorite::with([
                'carListing.brand',
                'carListing.carModel',
                'carListing.bodyType',
                'carListing.location',
                'carListing.media',
                'carListing.badges',
                'carListing.carCondition',
                'carListing.fuelType',
                'carListing.transmission',
                'carListing.exteriorColor',
                'carListing.driveType',
                'carListing.cylinder'
            ])->where('user_id', $user->id);

            // Apply sorting
            $sortBy = $request->get('sort', 'latest');
            switch ($sortBy) {
                case 'latest':
                    $query->orderByRecentlyAdded();
                    break;
                case 'recently_viewed':
                    $query->orderByRecentlyViewed();
                    break;
                case 'price_high':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->orderBy('car_listings.price', 'desc');
                    break;
                case 'price_low':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->orderBy('car_listings.price', 'asc');
                    break;
                case 'year_high':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->orderBy('car_listings.year', 'desc');
                    break;
                case 'year_low':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->orderBy('car_listings.year', 'asc');
                    break;
                case 'mileage_high':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->orderBy('car_listings.mileage', 'desc');
                    break;
                case 'mileage_low':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->orderBy('car_listings.mileage', 'asc');
                    break;
                default:
                    $query->orderByRecentlyAdded();
            }

            $favorites = $query->paginate(20);

            $data = [
                'watch_list' => $favorites->items(),
                'total' => $favorites->total(),
                'per_page' => $favorites->perPage(),
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'count' => $favorites->total(),
            ];

            return $this->successResponse($data, 'Watch list retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve watch list: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's search history
     */
    public function getSearchHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $query = SearchHistory::where('user_id', $user->id);

            // Get saved searches or all history
            if ($request->get('saved_only', false)) {
                $query->saved();
            }

            $searches = $query->orderBy('last_searched_at', 'desc')->paginate(20);

            $data = [
                'search_history' => $searches->items(),
                'total' => $searches->total(),
                'per_page' => $searches->perPage(),
                'current_page' => $searches->currentPage(),
                'last_page' => $searches->lastPage(),
            ];

            return $this->successResponse($data, 'Search history retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve search history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Save a search query
     */
    public function saveSearch(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $searchHistory = SearchHistory::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'search_query' => $request->search_query,
                'filters' => $request->filters,
                'sort_options' => $request->sort_options,
                'is_saved' => true,
                'notifications_enabled' => $request->notifications_enabled ?? false,
                'last_searched_at' => now(),
            ]);

            return $this->successResponse($searchHistory, 'Search saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save search: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a saved search
     */
    public function deleteSearch($searchId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $search = SearchHistory::where('user_id', $user->id)
                ->where('id', $searchId)
                ->first();

            if (!$search) {
                return $this->notFoundResponse('Search not found');
            }

            $search->delete();

            return $this->successResponse(null, 'Search deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete search: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle notifications for a saved search
     */
    public function toggleSearchNotifications($searchId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $search = SearchHistory::where('user_id', $user->id)
                ->where('id', $searchId)
                ->first();

            if (!$search) {
                return $this->notFoundResponse('Search not found');
            }

            $search->update([
                'notifications_enabled' => !$search->notifications_enabled
            ]);

            return $this->successResponse($search, 'Notifications toggled successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle notifications: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add a car listing to watch list
     */
    public function addToWatchList(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'car_listing_id' => 'required|exists:car_listings,id',
                'notes' => 'nullable|string|max:500',
                'notifications_enabled' => 'boolean'
            ]);

            // Check if already in watch list
            $existingFavorite = Favorite::where('user_id', $user->id)
                ->where('car_listing_id', $request->car_listing_id)
                ->first();

            if ($existingFavorite) {
                return $this->errorResponse('Car listing is already in your watch list', 400);
            }

            $carListing = \App\Models\CarListing::find($request->car_listing_id);
            
            $favorite = Favorite::create([
                'user_id' => $user->id,
                'car_listing_id' => $request->car_listing_id,
                'notes' => $request->notes,
                'notifications_enabled' => $request->notifications_enabled ?? false,
                'price_when_added' => $carListing->price,
                'viewed_at' => now(),
            ]);

            $favorite->load([
                'carListing.brand',
                'carListing.carModel',
                'carListing.bodyType',
                'carListing.location',
                'carListing.media',
                'carListing.badges'
            ]);

            return $this->successResponse($favorite, 'Added to watch list successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add to watch list: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove a car listing from watch list
     */
    public function removeFromWatchList($favoriteId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $favorite = Favorite::where('user_id', $user->id)
                ->where('id', $favoriteId)
                ->first();

            if (!$favorite) {
                return $this->notFoundResponse('Favorite not found');
            }

            $favorite->delete();

            return $this->successResponse(null, 'Removed from watch list successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove from watch list: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle notifications for a favorite
     */
    public function toggleFavoriteNotifications($favoriteId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $favorite = Favorite::where('user_id', $user->id)
                ->where('id', $favoriteId)
                ->first();

            if (!$favorite) {
                return $this->notFoundResponse('Favorite not found');
            }

            $favorite->update([
                'notifications_enabled' => !$favorite->notifications_enabled
            ]);

            return $this->successResponse($favorite, 'Notifications toggled successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle notifications: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sort options for watch list
     */
    public function getSortOptions(): JsonResponse
    {
        try {
            $sortOptions = [
                ['value' => 'latest', 'label' => 'Latest'],
                ['value' => 'recently_viewed', 'label' => 'Recently Viewed'],
                ['value' => 'price_high', 'label' => 'Price (High to Low)'],
                ['value' => 'price_low', 'label' => 'Price (Low to High)'],
                ['value' => 'year_high', 'label' => 'Year (High to Low)'],
                ['value' => 'year_low', 'label' => 'Year (Low to High)'],
                ['value' => 'mileage_high', 'label' => 'Mileage (High to Low)'],
                ['value' => 'mileage_low', 'label' => 'Mileage (Low to High)'],
            ];

            return $this->successResponse($sortOptions, 'Sort options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sort options: ' . $e->getMessage(), 500);
        }
    }
}
