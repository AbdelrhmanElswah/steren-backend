<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimilarCarsResource extends JsonResource
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
            'created_at' => $this->created_at?->toISOString(),
            
            // Relationships
            'brand' => $this->when($this->brand, [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'logo' => $this->brand?->media ? asset('storage/' . $this->brand->media->path_file_name) : null,
            ]),
            
            'car_model' => $this->when($this->carModel, [
                'id' => $this->carModel?->id,
                'name' => $this->carModel?->name,
            ]),
            
            'body_type' => $this->when($this->bodyType, [
                'id' => $this->bodyType?->id,
                'name' => $this->bodyType?->name,
                'icon' => $this->bodyType?->media ? asset('storage/' . $this->bodyType->media->path_file_name) : null,
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
            'primary_image' => $this->when($this->media && $this->media->count() > 0, function () {
                $primaryMedia = $this->media->where('order', 1)->first() ?? $this->media->first();
                return [
                    'id' => $primaryMedia->id,
                    'url' => asset('storage/' . $primaryMedia->path_file_name),
                    'alt_text' => $primaryMedia->alt_text ?? '',
                ];
            }),
            
            // Badges
            'badges' => $this->active_badges,
            
            // Similarity score (if available)
            'similarity_score' => $this->similarity_score ?? null,
            
            // Analytics
            'views_count' => $this->total_views ?? 0,
        ];
    }
}
