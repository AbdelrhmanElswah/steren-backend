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
            'doors' => $this->doors,
            'mileage' => $this->mileage,
            'mileage_type' => $this->mileageType?->name,
            'formatted_mileage' => $this->formatted_mileage,
            'origin_country' => $this->origin_country,
            'gcc_import' => $this->gcc_import,
            'plate_status' => $this->plate_status,
            'condition_type' => $this->condition_type,
            'has_warranty' => $this->has_warranty,
            'is_inspected' => $this->is_inspected,
            'is_verified' => $this->is_verified,
            'has_insurance' => $this->has_insurance,
            'is_featured' => $this->is_featured,
            'is_new_arrival' => $this->is_new_arrival,
            'is_promoted' => $this->is_promoted,
            'status' => $this->status,
            'operator_decision' => $this->operator_decision,
            'views' => $this->views,
            'formatted_views' => $this->formatted_views,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Engine and performance specs
            'engine_size' => $this->engine_size,
            'engine_type' => $this->engine_type,
            'horsepower' => $this->horsepower,
            'torque' => $this->torque,
            'acceleration_0_100' => $this->acceleration_0_100,
            'top_speed' => $this->top_speed,
            'seating_capacity' => $this->seating_capacity,
            'mileage_efficiency' => $this->mileage_efficiency,
            'formatted_mileage_efficiency' => $this->formatted_mileage_efficiency,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'formatted_dimensions' => $this->formatted_dimensions,
            
            // VIN and identification
            'vin' => $this->vin,
            
            // Relationships
            'brand' => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'logo' => $this->brand?->logo,
            ],
            'car_model' => [
                'id' => $this->carModel?->id,
                'name' => $this->carModel?->name,
            ],
            'body_type' => [
                'id' => $this->bodyType?->id,
                'name' => $this->bodyType?->name,
            ],
            'location' => [
                'id' => $this->location?->id,
                'name' => $this->location?->name,
                'city' => $this->location?->city?->name,
            ],
            'studio' => [
                'id' => $this->studio?->id,
                'name' => $this->studio?->name,
                'address' => $this->studio?->address,
            ],
            'car_condition' => [
                'id' => $this->carCondition?->id,
                'name' => $this->carCondition?->name,
            ],
            'fuel_type' => [
                'id' => $this->fuelType?->id,
                'name' => $this->fuelType?->name,
            ],
            'transmission' => [
                'id' => $this->transmission?->id,
                'name' => $this->transmission?->name,
            ],
            'exterior_color' => [
                'id' => $this->exteriorColor?->id,
                'name' => $this->exteriorColor?->name,
                'hex_code' => $this->exteriorColor?->hex_code,
            ],
            'drive_type' => [
                'id' => $this->driveType?->id,
                'name' => $this->driveType?->name,
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
            
            // Badges and status
            'badges' => $this->badges ? $this->badges->map(function ($badge) {
                return [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon' => $badge->icon,
                    'color' => $badge->color,
                    'is_active' => $badge->pivot->is_active ?? true,
                ];
            }) : collect(),
            'active_badges' => $this->active_badges ?? [],
            'status_badge' => $this->status_badge ?? null,
            
            // Features and specifications
            'features' => $this->features ? $this->features->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'icon' => $feature->icon,
                    'category' => $feature->category,
                    'is_included' => $feature->pivot->is_included ?? false,
                    'notes' => $feature->pivot->notes,
                ];
            }) : collect(),
            'specifications' => $this->specifications ? $this->specifications->map(function ($spec) {
                return [
                    'id' => $spec->id,
                    'name' => $spec->name,
                    'value' => $spec->value,
                    'unit' => $spec->unit,
                    'formatted_value' => $spec->formatted_value,
                    'description' => $spec->description,
                    'specification_type' => $spec->specification_type,
                    'is_highlighted' => $spec->is_highlighted,
                ];
            }) : collect(),
            
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
            'promotion_price_paid' => $this->promotion_price_paid,
            'promotion_currency' => $this->promotion_currency,
            'promotion_expires_at' => $this->promotion_expires_at?->toISOString(),
            'promotion_started_at' => $this->promotion_started_at?->toISOString(),
            
            // Analytics
            'total_views' => $this->total_views,
            'total_shares' => $this->total_shares,
            'total_call_taps' => $this->total_call_taps,
            
            // Full title for display
            'full_title' => $this->full_title,
            
            // Damage status
            'damage_status' => $this->damage_status,
        ];
    }
}
