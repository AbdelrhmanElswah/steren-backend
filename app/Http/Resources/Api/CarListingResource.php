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
            ? $media->sortBy('order')->first() 
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
            'gcc_import' => $this->gcc_import,
            'has_warranty' => $this->has_warranty,
            'is_inspected' => $this->is_inspected,
            'is_verified' => $this->is_verified,
            'has_insurance' => $this->has_insurance,
            'is_featured' => $this->is_featured,
            'is_new_arrival' => $this->is_new_arrival,
            'status' => $this->status,
            'views' => $this->views,
            'formatted_views' => $this->formatted_views,
            'city' => $this->whenLoaded('city', [
                'id' => $this->city?->id,
                'name' => $this->city?->name,
            ]),
            'brand' => $this->whenLoaded('brand', [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
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
            'seller' => $this->whenLoaded('user', function () {
                $profilePicture = null;

                if ($this->user->relationLoaded('profilePictureMedia') && $this->user->profilePictureMedia) {
                    $profilePicture = $this->user->profilePictureMedia->public_url ?? $this->user->profilePictureMedia->path_file_name;
                } elseif (!empty($this->user->profile_picture)) {
                    $profilePicture = $this->user->profile_picture;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->display_name ?? $this->user->full_name,
                    'profile_picture' => $profilePicture,
                    'is_verified' => (bool) $this->user->is_verified,
                    'is_dealer' => (bool) $this->user->is_dealer,
                ];
            }),
        ];
    }
}
