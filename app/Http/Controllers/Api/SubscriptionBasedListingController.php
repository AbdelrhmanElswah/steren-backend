<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionBasedListingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionBasedListingController extends Controller
{
    use ApiResponse;

    protected SubscriptionBasedListingService $listingService;

    public function __construct(SubscriptionBasedListingService $listingService)
    {
        $this->listingService = $listingService;
    }

    /**
     * Get car listings based on user's subscription plan
     */
    public function getCarListings(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->all();

            $listings = $this->listingService->getCarListingsForUser($user, $filters);

            return $this->successResponse([
                'listings' => $listings->items(),
                'pagination' => [
                    'current_page' => $listings->currentPage(),
                    'last_page' => $listings->lastPage(),
                    'per_page' => $listings->perPage(),
                    'total' => $listings->total(),
                ],
                'filters_applied' => $filters,
            ], 'Car listings retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve car listings: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get top picks for user based on subscription plan
     */
    public function getTopPicks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = $request->input('limit', 10);

            $topPicks = $this->listingService->getTopPicksForUser($user, $limit);

            return $this->successResponse([
                'top_picks' => $topPicks,
                'count' => $topPicks->count(),
            ], 'Top picks retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve top picks: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get "You May Also Like" recommendations
     */
    public function getYouMayAlsoLike(Request $request, int $listingId): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = $request->input('limit', 6);

            $listing = \App\Models\CarListing::findOrFail($listingId);
            $recommendations = $this->listingService->getYouMayAlsoLikeForUser($user, $listing, $limit);

            return $this->successResponse([
                'recommendations' => $recommendations,
                'count' => $recommendations->count(),
                'original_listing' => [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'brand' => $listing->brand?->name,
                    'model' => $listing->carModel?->name,
                    'year' => $listing->year,
                ],
            ], 'Recommendations retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve recommendations: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get dealer's studios
     */
    public function getDealerStudios(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user is a dealer (basic validation for studio access)
            if (!$user->isDealer()) {
                return $this->errorResponse('Only dealers can access studios. Please complete your dealer application.', 403);
            }

            $studios = $this->listingService->getDealerStudios($user);

            return $this->successResponse([
                'studios' => $studios,
                'count' => $studios->count(),
            ], 'Dealer studios retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dealer studios: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a new studio for dealer
     */
    public function createStudio(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'required|string|max:500',
                'city_id' => 'required|exists:cities,id',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'website' => 'nullable|url|max:255',
                'settings' => 'nullable|array',
                'social_links' => 'nullable|array',
                'business_hours' => 'nullable|array',
                'amenities' => 'nullable|array',
                'specialties' => 'nullable|array',
            ]);

            $studio = $this->listingService->createStudio($user, $request->all());

            return $this->successResponse([
                'studio' => $studio,
            ], 'Studio created successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create studio: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get car listings for a specific dealer studio
     */
    public function getStudioListings(Request $request, int $studioId): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->all();

            $listings = $this->listingService->getCarListingsForDealerStudio($user, $studioId, $filters);

            return $this->successResponse([
                'listings' => $listings->items(),
                'pagination' => [
                    'current_page' => $listings->currentPage(),
                    'last_page' => $listings->lastPage(),
                    'per_page' => $listings->perPage(),
                    'total' => $listings->total(),
                ],
                'studio_id' => $studioId,
                'filters_applied' => $filters,
            ], 'Studio car listings retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve studio listings: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Associate car listing with studio
     */
    public function associateListingWithStudio(Request $request, int $listingId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'studio_id' => 'required|exists:dealer_studios,id',
            ]);

            $listing = \App\Models\CarListing::where('id', $listingId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $updatedListing = $this->listingService->associateListingWithStudio($listing, $request->studio_id);

            return $this->successResponse([
                'listing' => $updatedListing,
            ], 'Car listing associated with studio successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to associate listing with studio: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get subscription plan features for current user
     */
    public function getPlanFeatures(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user is a dealer (basic validation for features)
            $isDealer = $user->isDealer();
            
            if (!$isDealer) {
                return $this->successResponse([
                    'plan' => null,
                    'features' => [
                        'can_view_basic_listings' => true,
                        'can_view_featured_listings' => false,
                        'can_view_sponsored_listings' => false,
                        'can_view_top_picks' => false,
                        'can_view_recommendations' => false,
                        'can_create_studios' => false,
                        'auto_studio_creation' => false,
                        'auto_listing_association' => false,
                        'auto_top_picks' => false,
                        'max_listings' => 0,
                        'max_photos_per_listing' => 5,
                    ],
                ], 'Free tier features');
            }

            // Dealer features (based on verification level)
            $features = [
                'can_view_basic_listings' => true,
                'can_view_featured_listings' => $isDealer, // Only dealers
                'can_view_sponsored_listings' => $isDealer, // Only dealers
                'can_view_top_picks' => $isDealer, // Only dealers
                'can_view_recommendations' => $isDealer, // Only dealers
                'can_create_studios' => $isDealer, // Only dealers
                'auto_studio_creation' => $isDealer, // Only dealers
                'auto_listing_association' => $isDealer, // Only dealers
                'auto_top_picks' => $isDealer, // Only dealers
                'max_listings' => $isDealer ? 0 : 10, // Unlimited for dealers, limited for basic users
                'max_photos_per_listing' => $isDealer ? 20 : 10, // More photos for dealers
                'has_analytics' => $isDealer, // Only dealers
                'has_priority_support' => $isDealer, // Only dealers
                'has_dedicated_account_rep' => $isDealer, // Only dealers
                'verification_status' => $isDealer ? 'dealer' : 'not_dealer',
            ];

            // Get auto-studio info for dealers
            $autoStudioInfo = null;
            if ($isDealer) {
                $defaultStudio = \App\Models\DealerStudio::where('dealer_id', $user->id)
                    ->where('name', 'Default Studio')
                    ->first();

                $autoStudioInfo = [
                    'has_default_studio' => $defaultStudio !== null,
                    'default_studio_id' => $defaultStudio?->id,
                    'auto_assignment_enabled' => $defaultStudio?->settings['auto_assign_listings'] ?? false,
                    'auto_featured_enabled' => $defaultStudio?->settings['auto_featured'] ?? false,
                ];
            }

            return $this->successResponse([
                'plan' => [
                    'id' => null,
                    'name' => 'Dealer',
                    'description' => 'Dealer with approved application',
                    'price' => 0,
                    'currency' => 'AED',
                ],
                'features' => $features,
                'auto_studio_info' => $autoStudioInfo,
            ], 'Dealer features retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve plan features: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get auto studio status for dealer
     */
    public function getAutoStudioStatus(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user is a dealer (basic validation for auto studio)
            if (!$user->isDealer()) {
                return $this->errorResponse('Only dealers can access auto studio features. Please complete your dealer application.', 403);
            }

            $defaultStudio = \App\Models\DealerStudio::where('dealer_id', $user->id)
                ->where('name', 'Default Studio')
                ->first();

            $totalListings = \App\Models\CarListing::where('user_id', $user->id)->count();
            $autoAssignedListings = \App\Models\CarListing::where('user_id', $user->id)
                ->where('studio_id', $defaultStudio?->id)
                ->count();
            $autoFeaturedListings = \App\Models\CarListing::where('user_id', $user->id)
                ->where('is_featured', true)
                ->count();

            return $this->successResponse([
                'auto_studio_status' => [
                    'has_default_studio' => $defaultStudio !== null,
                    'default_studio_id' => $defaultStudio?->id,
                    'default_studio_name' => $defaultStudio?->name,
                    'auto_assignment_enabled' => $defaultStudio?->settings['auto_assign_listings'] ?? false,
                    'auto_featured_enabled' => $defaultStudio?->settings['auto_featured'] ?? false,
                    'created_at' => $defaultStudio?->created_at,
                ],
                'listings_status' => [
                    'total_listings' => $totalListings,
                    'auto_assigned_listings' => $autoAssignedListings,
                    'auto_featured_listings' => $autoFeaturedListings,
                    'unassigned_listings' => $totalListings - $autoAssignedListings,
                    'non_featured_listings' => $totalListings - $autoFeaturedListings,
                ],
            ], 'Auto studio status retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve auto studio status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Process auto studio for existing listings
     */
    public function processAutoStudio(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user is a dealer (basic validation for auto studio processing)
            if (!$user->isDealer()) {
                return $this->errorResponse('Only dealers can process auto studio. Please complete your dealer application.', 403);
            }

            $autoStudioService = app(\App\Services\AutoStudioService::class);
            $autoStudioService->processExistingDealerListings($user);

            $defaultStudio = \App\Models\DealerStudio::where('dealer_id', $user->id)
                ->where('name', 'Default Studio')
                ->first();

            $processedListings = \App\Models\CarListing::where('user_id', $user->id)
                ->where('studio_id', $defaultStudio?->id)
                ->count();

            $featuredListings = \App\Models\CarListing::where('user_id', $user->id)
                ->where('is_featured', true)
                ->count();

            return $this->successResponse([
                'processed_listings' => $processedListings,
                'created_studio' => $defaultStudio !== null,
                'featured_listings' => $featuredListings,
                'assigned_listings' => $processedListings,
            ], 'Auto studio processing completed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to process auto studio: ' . $e->getMessage(),
                500
            );
        }
    }
}
