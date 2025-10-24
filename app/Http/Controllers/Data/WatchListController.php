<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\SearchHistory;
use App\Traits\ApiResponse;
use App\Traits\NotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WatchListController extends Controller
{
    use ApiResponse, NotificationTrait;

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
            ])->where('favorites.user_id', $user->id);

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
                          ->where('favorites.user_id', $user->id)
                          ->orderBy('car_listings.price', 'desc');
                    break;
                case 'price_low':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->where('favorites.user_id', $user->id)
                          ->orderBy('car_listings.price', 'asc');
                    break;
                case 'year_high':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->where('favorites.user_id', $user->id)
                          ->orderBy('car_listings.year', 'desc');
                    break;
                case 'year_low':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->where('favorites.user_id', $user->id)
                          ->orderBy('car_listings.year', 'asc');
                    break;
                case 'mileage_high':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->where('favorites.user_id', $user->id)
                          ->orderBy('car_listings.mileage', 'desc');
                    break;
                case 'mileage_low':
                    $query->join('car_listings', 'favorites.car_listing_id', '=', 'car_listings.id')
                          ->where('favorites.user_id', $user->id)
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

            // Send notification to user about saved search
            $this->sendSearchSavedNotification($searchHistory, $user);

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

            // Send notification to user about adding to watch list
            $this->sendWatchListAddedNotification($favorite, $user);

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

    /**
     * Send notification to user about saved search
     */
    private function sendSearchSavedNotification(SearchHistory $searchHistory, $user)
    {
        try {
            // Get or create search saved notification type
            $notificationType = \App\Models\NotificationType::firstOrCreate(
                ['name' => 'search_saved'],
                [
                    'name' => 'search_saved',
                    'display_name' => 'Search Saved',
                    'description' => 'Your search has been saved successfully',
                    'icon' => 'bookmark',
                    'color' => '#3B82F6',
                    'is_active' => true,
                    'default_template' => [
                        'title' => 'Search Saved Successfully!',
                        'body' => 'Your search "{{search_title}}" has been saved. You can find it in your saved searches.'
                    ],
                    'channels' => ['push'],
                    'sort_order' => 16
                ]
            );

            // Prepare notification data
            $title = 'Search Saved Successfully!';
            $body = "Your search \"{$searchHistory->title}\" has been saved successfully. ";
            
            if ($searchHistory->notifications_enabled) {
                $body .= "You'll receive notifications when new cars matching your criteria are listed.";
            } else {
                $body .= "You can enable notifications to get alerts about new matching cars.";
            }

            // Send notification to user
            $this->sendNotificationToUser(
                $user->id,
                $title,
                $body,
                $notificationType->id,
                [
                    'search_history_id' => $searchHistory->id,
                    'search_title' => $searchHistory->title,
                    'search_query' => $searchHistory->search_query,
                    'notifications_enabled' => $searchHistory->notifications_enabled,
                    'filters' => $searchHistory->filters,
                    'saved_at' => now()->toISOString()
                ]
            );

            \Illuminate\Support\Facades\Log::info('Search saved notification sent', [
                'user_id' => $user->id,
                'search_history_id' => $searchHistory->id,
                'search_title' => $searchHistory->title,
                'notifications_enabled' => $searchHistory->notifications_enabled
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send search saved notification', [
                'search_history_id' => $searchHistory->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to user about adding to watch list
     */
    private function sendWatchListAddedNotification($favorite, $user)
    {
        try {
            // Get or create watch list added notification type
            $notificationType = \App\Models\NotificationType::firstOrCreate(
                ['name' => 'watchlist_added'],
                [
                    'name' => 'watchlist_added',
                    'display_name' => 'Added to Watch List',
                    'description' => 'A car has been added to your watch list',
                    'icon' => 'favorite',
                    'color' => '#EC4899',
                    'is_active' => true,
                    'default_template' => [
                        'title' => 'Added to Watch List!',
                        'body' => '{{car_name}} has been added to your watch list.'
                    ],
                    'channels' => ['push'],
                    'sort_order' => 17
                ]
            );

            // Get car name for notification
            $carName = $favorite->carListing->title;
            if ($favorite->carListing->brand && $favorite->carListing->carModel) {
                $carName = $favorite->carListing->brand->name . ' ' . $favorite->carListing->carModel->name . ' ' . $favorite->carListing->year;
            }

            // Prepare notification data
            $title = 'Added to Watch List!';
            $body = "Great choice! {$carName} has been added to your watch list. ";
            
            if ($favorite->notifications_enabled) {
                $body .= "You'll receive notifications about price changes and updates for this car.";
            } else {
                $body .= "You can enable notifications to get alerts about price changes and updates.";
            }

            // Send notification to user
            $this->sendNotificationToUser(
                $user->id,
                $title,
                $body,
                $notificationType->id,
                [
                    'favorite_id' => $favorite->id,
                    'car_listing_id' => $favorite->car_listing_id,
                    'car_name' => $carName,
                    'price' => $favorite->carListing->price,
                    'currency' => $favorite->carListing->price_currency,
                    'notifications_enabled' => $favorite->notifications_enabled,
                    'added_at' => now()->toISOString()
                ]
            );

            \Illuminate\Support\Facades\Log::info('Watch list added notification sent', [
                'user_id' => $user->id,
                'favorite_id' => $favorite->id,
                'car_listing_id' => $favorite->car_listing_id,
                'car_name' => $carName,
                'notifications_enabled' => $favorite->notifications_enabled
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send watch list added notification', [
                'favorite_id' => $favorite->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
