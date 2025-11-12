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
        // Pre-calculate expensive operations
        $media = $this->relationLoaded('media') ? $this->media : null;
        $primaryMedia = $media && $media instanceof \Illuminate\Database\Eloquent\Collection 
            ? $media->where('info', 'primary')->sortBy('order')->first() 
            : null;
        
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'price_currency' => $this->price_currency,
            'formatted_price' => $this->formatted_price,
            'mileage' => $this->mileage,
            'mileage_type' => $this->whenLoaded('mileageType', fn() => $this->mileageType?->name),
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
            'location' => $this->whenLoaded('location', [
                'id' => $this->location?->id,
                'name' => $this->location?->name,
            ]),
            'brand' => $this->whenLoaded('brand', [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'odoo_id' => $this->brand?->odoo_id,
            ]),
            'carModel' => $this->whenLoaded('carModel', [
                'id' => $this->carModel?->id,
                'name' => $this->carModel?->name,
                'car_brand_id' => $this->carModel?->car_brand_id,
            ]),
            'bodyType' => $this->whenLoaded('bodyType', [
                'id' => $this->bodyType?->id,
                'name' => $this->bodyType?->name,
            ]),
            // Odoo integration data
            'odoo_id' => $this->odoo_id,
            'odoo_listing_id' => $this->odoo_listing_id,
            'odoo_vehicle_id' => $this->odoo_vehicle_id,
            'odoo_seller_id' => $this->odoo_seller_id,
            'odoo_city_id' => $this->odoo_city_id,
            'odoo_listing_state' => $this->odoo_listing_state,
            'odoo_response' => $this->when($this->odoo_response, $this->odoo_response),
            // Optimized media handling
            'media' => $this->whenLoaded('media', function() use ($primaryMedia) {
                if (!$primaryMedia) {
                    return [];
                }

                return [[
                    'id' => $primaryMedia->id,
                    'file_path' => $primaryMedia->path_file_name,
                    'url' => $primaryMedia->public_url ?? $primaryMedia->path_file_name,
                    'file_type' => $primaryMedia->file_type,
                    'is_primary' => true,
                    'order' => $primaryMedia->order ?? 0,
                    'info' => $primaryMedia->info,
                ]];
            }),
        ];
    }
}
