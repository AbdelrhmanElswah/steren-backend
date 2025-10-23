<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ResourceMediaTrait;

class SearchResultResource extends JsonResource
{
    use ResourceMediaTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Pre-calculate expensive operations
        $media = $this->whenLoaded('media');
        $primaryImage = $media ? $media->where('info', 'primary')->first()?->path_file_name : null;
        $images = $media ? $media->pluck('path_file_name')->toArray() : [];
        
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
            // Odoo integration data
            'odoo_id' => $this->odoo_id,
            'odoo_listing_id' => $this->odoo_listing_id,
            'odoo_vehicle_id' => $this->odoo_vehicle_id,
            'odoo_seller_id' => $this->odoo_seller_id,
            'odoo_city_id' => $this->odoo_city_id,
            'odoo_listing_state' => $this->odoo_listing_state,
            'odoo_response' => $this->when($this->odoo_response, $this->odoo_response),
            // Optimized media handling
            'media' => $this->whenLoaded('media', function() use ($media) {
                return $media ? $media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'file_path' => $media->path_file_name,
                        'url' => $media->path_file_name, // Use path_file_name as URL for now
                        'file_type' => $media->file_type,
                        'is_primary' => $media->info === 'primary',
                        'order' => $media->order ?? 0,
                        'info' => $media->info,
                    ];
                }) : collect();
            }),
        ];
    }
}
