<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'price_currency' => $this->price_currency,
            'formatted_price' => $this->formatted_price,
            'year' => $this->year,
            'trim' => $this->trim,
            'mileage' => $this->mileage,
            'mileage_type' => $this->mileageType?->name,
            'formatted_mileage' => $this->formatted_mileage,
            'origin_country' => $this->origin_country,
            'has_warranty' => $this->has_warranty,
            'is_inspected' => $this->is_inspected,
            'is_verified' => $this->is_verified,
            'has_insurance' => $this->has_insurance,
            'is_featured' => $this->is_featured,
            'is_new_arrival' => $this->is_new_arrival,
            'is_promoted' => $this->is_promoted,
            'status' => $this->status,
            'views' => $this->views,
            'formatted_views' => $this->formatted_views,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
                        
            'car_model' => [
                'id' => $this->carModel?->id,
                'name' => $this->carModel?->name,
            ],
            'location' => [
                'id' => $this->location?->id,
                'name' => $this->location?->name,
                'city' => $this->location?->city?->name,
            ],

            'cylinder' => [
                'id' => $this->cylinder?->id,
                'name' => $this->cylinder?->name,
            ],
            'user' => [
                'id' => $this->user?->id,
                'full_name' => $this->user?->full_name,
                'display_name' => $this->user?->display_name,
                'phone' => $this->user?->phone,
                'email' => $this->user?->email,
            ],
            
            // Media and images
            'media' => $this->media ? $this->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'file_path' => $media->file_path,
                    'url' => $media->url,
                    'file_type' => $media->file_type,
                    'is_primary' => $media->is_primary,
                    'file_size' => $media->file_size,
                    'mime_type' => $media->mime_type,
                ];
            }) : collect(),
            'primary_image' => $this->media ? $this->media->where('is_primary', true)->first()?->url : null,
            'images' => $this->media ? $this->media->pluck('url')->toArray() : [],
            
            // Promotion information
            'listing_promotion' => [
                'id' => $this->listingPromotion?->id,
                'name' => $this->listingPromotion?->name,
                'type' => $this->listingPromotion?->type,
                'price' => $this->listingPromotion?->price,
                'currency' => $this->listingPromotion?->currency,
                'duration_days' => $this->listingPromotion?->duration_days,
                'is_active' => $this->listingPromotion?->is_active,
            ],
            'full_title' => $this->full_title,
        ];
    }
}
