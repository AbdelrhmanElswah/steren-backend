<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
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
            'title' => $this->title,
            'full_title' => $this->full_title,
            'price' => (float) $this->price,
            'formatted_price' => $this->formatted_price,
            'year' => $this->year,
            'mileage' => $this->mileage,
            'formatted_mileage' => $this->formatted_mileage,
            'condition_type' => $this->condition_type,
            'has_warranty' => $this->has_warranty,
            'is_inspected' => $this->is_inspected,
            'is_verified' => $this->is_verified,
            'is_sponsored' => $this->is_sponsored,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'created_at' => $this->created_at?->toISOString(),
            
            // Relationships
            'brand' => $this->when($this->brand, [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'logo' => $this->brand?->media ? asset('storage/' . $this->brand->media->file_path) : null,
            ]),
            
            'car_model' => $this->when($this->carModel, [
                'id' => $this->carModel?->id,
                'name' => $this->carModel?->name,
            ]),
            
            'body_type' => $this->when($this->bodyType, [
                'id' => $this->bodyType?->id,
                'name' => $this->bodyType?->name,
                'icon' => $this->bodyType?->media ? asset('storage/' . $this->bodyType->media->file_path) : null,
            ]),
            
            'location' => $this->when($this->location, [
                'id' => $this->location?->id,
                'city' => $this->location?->city?->name,
                'area' => $this->location?->area,
            ]),
            
            'fuel_type' => $this->when($this->fuelType, [
                'id' => $this->fuelType?->id,
                'name' => $this->fuelType?->name,
            ]),
            
            'transmission' => $this->when($this->transmission, [
                'id' => $this->transmission?->id,
                'name' => $this->transmission?->name,
            ]),
            
            'exterior_color' => $this->when($this->exteriorColor, [
                'id' => $this->exteriorColor?->id,
                'name' => $this->exteriorColor?->name,
                'hex_code' => $this->exteriorColor?->hex_code,
            ]),
            
            'drive_type' => $this->when($this->driveType, [
                'id' => $this->driveType?->id,
                'name' => $this->driveType?->name,
            ]),
            
            // Media
            'images' => $this->when($this->media, $this->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => asset('storage/' . $media->file_path),
                    'alt_text' => $media->alt_text,
                    'is_primary' => $media->sort_order === 1,
                ];
            })),
            
            'primary_image' => $this->when($this->media && $this->media->count() > 0, function () {
                $primaryMedia = $this->media->where('sort_order', 1)->first() ?? $this->media->first();
                return [
                    'id' => $primaryMedia->id,
                    'url' => asset('storage/' . $primaryMedia->file_path),
                    'alt_text' => $primaryMedia->alt_text,
                ];
            }),
            
            // Badges
            'badges' => $this->active_badges,
            'status_badge' => $this->status_badge,
            
            // Analytics
            'views_count' => $this->total_views ?? 0,
            'days_active' => $this->days_active ?? 0,
        ];
    }
}
