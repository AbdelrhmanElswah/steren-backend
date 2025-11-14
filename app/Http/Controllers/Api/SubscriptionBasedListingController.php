<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CarListingResource;
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
                'listings' => CarListingResource::collection($listings->items()),
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
     * Get top picks (public endpoint - no auth required)
     * Returns general top picks for all users
     */
    public function getTopPicks(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $filters = array_filter(
                $request->only(['city_id']),
                fn ($value) => $value !== null && $value !== ''
            );

            // Build cache key based on limit and filters (no user ID since it's public)
            // Use city_id directly in cache key for better cache differentiation
            $cityId = isset($filters['city_id']) ? '_city_' . (int) $filters['city_id'] : '';
            $cacheKey = 'top_picks_public_' . $limit . $cityId . '_' . md5(serialize($filters));
            
            // Cache for 24 hours (86400 seconds) - longer cache since it's public data
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($limit, $filters) {
                // Always get general top picks (not user-specific)
                $topPicks = $this->listingService->getGeneralTopPicks($limit, $filters);
                
                // Load all cities once and create a map by name to avoid N+1 queries
                $citiesByName = \App\Models\City::all()->keyBy('name');
                
                // Build response directly without using resource (faster, avoids temporary URL generation)
                $topPicksCollection = $topPicks->map(function ($listing) use ($citiesByName) {
                    // Get primary media
                    $mediaLink = null;
                    $media = $listing->media?->firstWhere('info', 'primary') ?? $listing->media?->first();
                    if ($media && $media->path_file_name) {
                        // Use public URL (already optimized, no S3 API calls)
                        $mediaLink = $media->getS3PublicUrl($media->path_file_name);
                    }
                    
                    // Get user profile picture
                    $profilePictureLink = null;
                    if ($listing->user && $listing->user->relationLoaded('profilePictureMedia')) {
                        $profileMedia = $listing->user->profilePictureMedia;
                        if ($profileMedia && $profileMedia->path_file_name) {
                            $profilePictureLink = $profileMedia->getS3PublicUrl($profileMedia->path_file_name);
                        }
                    }

                    // Get city by matching location name (since locations are matched to cities by name)
                    $city = null;
                    if ($listing->location && $listing->location->name) {
                        $city = $citiesByName->get($listing->location->name);
                    }

                    return [
                        'id' => $listing->id,
                        'odoo_id' => $listing->odoo_id,
                        'title' => $listing->title,
                        'price' => $listing->price,
                        'year' => $listing->year,
                        'mileage' => $listing->mileage,
                        'views' => $listing->views ?? $listing->total_views ?? 0,
                        'formatted_views' => $listing->formatted_views ?? '0 Views',
                        'brand' => $listing->brand ? ['id' => $listing->brand->id, 'name' => $listing->brand->name] : null,
                        'car_model' => $listing->carModel ? ['id' => $listing->carModel->id, 'name' => $listing->carModel->name] : null,
                        'body_type' => $listing->bodyType ? ['id' => $listing->bodyType->id, 'name' => $listing->bodyType->name] : null,
                        'city' => $city ? ['id' => $city->id, 'name' => $city->name] : null,
                        'studio' => $listing->studio ? ['id' => $listing->studio->id, 'name' => $listing->studio->name] : null,
                        'is_featured' => $listing->is_featured,
                        'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                        'user' => $listing->user ? [
                            'id' => $listing->user->id,
                            'name' => $listing->user->full_name,
                            'profile_picture' => $profilePictureLink ? ['media_link' => $profilePictureLink] : null,
                        ] : null,
                        'created_at' => $listing->created_at?->toISOString(),
                    ];
                })->toArray();

                return [
                    'top_picks' => $topPicksCollection,
                    'count' => count($topPicksCollection),
                ];
            });

            return $this->successResponse([
                'top_picks' => $result['top_picks'],
                'count' => $result['count'],
                'filters_applied' => $filters,
            ], 'Top picks retrieved successfully');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SubscriptionBasedListingController: Error fetching top picks', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $filters ?? []
            ]);
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
                'recommendations' => CarListingResource::collection($recommendations),
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
     * Get all studios (public endpoint) with their car listings
     */
    public function getAllStudios(Request $request): JsonResponse
    {
        try {
            $filters = array_filter(
                $request->only(['city_id', 'verified_only', 'sort_by']),
                fn ($value) => $value !== null && $value !== ''
            );
            $studios = $this->listingService->getAllStudios($filters);

            // Format studios with their listings
            $formattedStudios = $studios->map(function ($studio) {
                return [
                    'id' => $studio->id,
                    'dealer_id' => $studio->dealer_id,
                    'name' => $studio->name,
                    'description' => $studio->description,
                    'address' => $studio->address,
                    'city_id' => $studio->city_id,
                    'phone' => $studio->phone,
                    'email' => $studio->email,
                    'website' => $studio->website,
                    'logo_url' => $studio->logo_url,
                    'cover_image_url' => $studio->cover_image_url,
                    'is_active' => $studio->is_active,
                    'is_verified' => $studio->is_verified,
                    'verification_status' => $studio->verification_status,
                    'settings' => $studio->settings,
                    'social_links' => $studio->social_links,
                    'business_hours' => $studio->business_hours,
                    'amenities' => $studio->amenities,
                    'specialties' => $studio->specialties,
                    'dealer' => $studio->dealer ? [
                        'id' => $studio->dealer->id,
                        'name' => $studio->dealer->name,
                        'email' => $studio->dealer->email,
                    ] : null,
                    'city' => $studio->city ? [
                        'id' => $studio->city->id,
                        'name' => $studio->city->name,
                    ] : null,
                    'car_listings' => CarListingResource::collection($studio->carListings),
                    'listings_count' => $studio->carListings->count(),
                    'created_at' => $studio->created_at,
                    'updated_at' => $studio->updated_at,
                ];
            });

            return $this->successResponse([
                'studios' => $formattedStudios,
                'count' => $formattedStudios->count(),
            ], 'Studios retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve studios: ' . $e->getMessage(),
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
                'listings' => CarListingResource::collection($listings->items()),
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
