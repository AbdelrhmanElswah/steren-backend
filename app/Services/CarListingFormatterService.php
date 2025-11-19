<?php

namespace App\Services;

use App\Models\CarListing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class CarListingFormatterService
{
    /**
     * Format a single car listing to match /api/car-listings format
     * 
     * @param CarListing $listing
     * @param int|null $mediaCount Optional media count (for performance, avoids counting query)
     * @return array
     */
    public function formatListing(CarListing $listing, ?int $mediaCount = null): array
    {
        // Get all media with only media_link (optimized for performance)
        $mediaList = [];
        if ($listing->relationLoaded('media') && $listing->media) {
            $mediaList = $listing->media->map(function ($media) {
                if ($media && $media->path_file_name) {
                    return [
                        'media_link' => $media->getS3PublicUrl($media->path_file_name)
                    ];
                }
                return null;
            })->filter()->values()->toArray();
        }

        // Get media count for gallery indicator
        if ($mediaCount === null) {
            $mediaCount = $listing->relationLoaded('media') 
                ? $listing->media->count() 
                : 0;
        }

        // Get user profile picture
        $profilePictureLink = null;
        if ($listing->user && $listing->user->relationLoaded('profilePictureMedia') && $listing->user->profilePictureMedia) {
            $profileMedia = $listing->user->profilePictureMedia;
            if ($profileMedia && $profileMedia->path_file_name) {
                $profilePictureLink = $profileMedia->getS3PublicUrl($profileMedia->path_file_name);
            }
        }

        // Build inspection_sides from loaded inspection relationship
        $inspectionSides = null;
        if ($listing->relationLoaded('inspection') && $listing->inspection) {
            $inspectionSides = [
                'right_side' => $listing->inspection->right_side ?? false,
                'left_side' => $listing->inspection->left_side ?? false,
                'roof' => $listing->inspection->roof ?? false,
                'front' => $listing->inspection->front ?? false,
                'back' => $listing->inspection->back ?? false,
            ];
        }

        return [
            'id' => $listing->id,
            'odoo_id' => $listing->odoo_id,
            'title' => $listing->title,
            'price' => $listing->price,
            'price_currency' => $listing->price_currency,
            'year' => $listing->year,
            'trim' => $listing->trim,
            'doors' => $listing->doors,
            'mileage' => $listing->mileage,
            'origin_country' => $listing->origin_country,
            'gcc_import' => $listing->gcc_import,
            'plate_status' => $listing->plate_status,
            'condition_type' => $listing->condition_type,
            'has_warranty' => $listing->has_warranty,
            'is_inspected' => $listing->is_inspected,
            'is_verified' => $listing->is_verified,
            'has_insurance' => $listing->has_insurance,
            'is_sponsored' => $listing->is_sponsored,
            'is_featured' => $listing->is_featured,
            'is_new_arrival' => $listing->is_new_arrival,
            'city_id' => $listing->city_id,
            'brand' => $listing->brand ? [
                'id' => $listing->brand->id,
                'name' => $listing->brand->name,
                'odoo_id' => $listing->brand->odoo_id,
            ] : null,
            'car_model' => $listing->carModel ? [
                'id' => $listing->carModel->id,
                'name' => $listing->carModel->name,
            ] : null,
            'body_type' => $listing->bodyType ? [
                'id' => $listing->bodyType->id,
                'name' => $listing->bodyType->name,
            ] : null,
            'city' => $listing->city ? [
                'id' => $listing->city->id,
                'name' => $listing->city->name,
                'odoo_id' => $listing->city->odoo_id,
            ] : null,
            'car_condition' => $listing->carCondition ? [
                'id' => $listing->carCondition->id,
                'name' => $listing->carCondition->name,
            ] : null,
            'fuel_type' => $listing->fuelType ? [
                'id' => $listing->fuelType->id,
                'name' => $listing->fuelType->name,
            ] : null,
            'transmission' => $listing->transmission ? [
                'id' => $listing->transmission->id,
                'name' => $listing->transmission->name,
            ] : null,
            'exterior_color' => $listing->exteriorColor ? [
                'id' => $listing->exteriorColor->id,
                'name' => $listing->exteriorColor->name,
            ] : null,
            'drive_type' => $listing->driveType ? [
                'id' => $listing->driveType->id,
                'name' => $listing->driveType->name,
            ] : null,
            'cylinder' => $listing->cylinder ? [
                'id' => $listing->cylinder->id,
                'name' => $listing->cylinder->name,
            ] : null,
            'media' => $mediaList, // All media with only media_link
            'media_count' => $mediaCount, // For UI gallery indicator (e.g., "1/12")
            'inspection_sides' => $inspectionSides,
            'user' => $listing->user ? [
                'id' => $listing->user->id,
                'full_name' => $listing->user->full_name,
                'profile_picture' => $profilePictureLink, // User profile picture
            ] : null,
        ];
    }

    /**
     * Format a collection of car listings
     * 
     * @param Collection|SupportCollection $listings
     * @param array|null $mediaCounts Optional array of [listing_id => count] for performance
     * @return array
     */
    public function formatListings($listings, ?array $mediaCounts = null): array
    {
        // If media counts provided, use them; otherwise calculate per listing
        if ($mediaCounts === null && $listings->isNotEmpty()) {
            $listingIds = $listings->pluck('id');
            $mediaCounts = \App\Models\Media::where('modelable_type', 'App\Models\CarListing')
                ->whereIn('modelable_id', $listingIds)
                ->selectRaw('modelable_id, COUNT(*) as count')
                ->groupBy('modelable_id')
                ->pluck('count', 'modelable_id')
                ->toArray();
        }

        return $listings->map(function ($listing) use ($mediaCounts) {
            $count = $mediaCounts[$listing->id] ?? null;
            return $this->formatListing($listing, $count);
        })->toArray();
    }
}

