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
        $useTemporaryUrl = $this->shouldUseTemporaryUrls($request);
        $expirationMinutes = $this->getExpirationMinutes($request);

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
            
            // User information - matching /car-listings format
            'user' => $this->when($this->user, [
                'id' => $this->user?->id,
                'full_name' => $this->user?->full_name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ]),
            'city' => $this->whenLoaded('city', [
                'id' => $this->city?->id,
                'name' => $this->city?->name,
            ]),
            
            // Relationships - matching /car-listings format
            'brand' => $this->when($this->brand, [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
            ]),
            
            'carModel' => $this->when($this->carModel, [
                'id' => $this->carModel?->id,
                'name' => $this->carModel?->name,
                'car_brand_id' => $this->carModel?->car_brand_id,
            ]),
            
            'bodyType' => $this->when($this->bodyType, [
                'id' => $this->bodyType?->id,
                'name' => $this->bodyType?->name,
            ]),
            
            'fuelType' => $this->when($this->fuelType, [
                'id' => $this->fuelType?->id,
                'name' => $this->fuelType?->name,
            ]),
            
            'transmission' => $this->when($this->transmission, [
                'id' => $this->transmission?->id,
                'name' => $this->transmission?->name,
            ]),
            
            'exteriorColor' => $this->when($this->exteriorColor, [
                'id' => $this->exteriorColor?->id,
                'name' => $this->exteriorColor?->name,
            ]),
            
            'driveType' => $this->when($this->driveType, [
                'id' => $this->driveType?->id,
                'name' => $this->driveType?->name,
            ]),
            
            'cylinder' => $this->when($this->cylinder, [
                'id' => $this->cylinder?->id,
                'name' => $this->cylinder?->name,
            ]),
            
            'carCondition' => $this->when($this->carCondition, [
                'id' => $this->carCondition?->id,
                'name' => $this->carCondition?->name,
            ]),
            
            // Media - using S3 URLs like Media model accessors
            'media' => $this->when(
                $this->relationLoaded('media') && $this->media->isNotEmpty(),
                $this->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'modelable_type' => $media->modelable_type,
                        'modelable_id' => $media->modelable_id,
                        'media' => $media->media,
                        'path_file_name' => $media->path_file_name,
                        'file_type' => $media->file_type,
                        'info' => $media->info,
                        'order' => $media->order,
                        'media_link' => $media->media_link, // S3 temporary URL
                        'media_temporary_url' => $media->media_temporary_url, // S3 temporary URL
                        'public_url' => $media->public_url, // S3 public URL
                        'file_size' => $media->file_size,
                        'mime_type' => $media->mime_type,
                        'exists' => $media->file_exists,
                    ];
                })
            ),
            
            // Badges
            'badges' => $this->active_badges,
            'status_badge' => $this->status_badge,
            
            // Analytics
            'views_count' => $this->total_views ?? 0,
            'days_active' => $this->days_active ?? 0,
        ];
    }
}
