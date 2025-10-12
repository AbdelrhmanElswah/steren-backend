<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SponsoredListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'title' => $this['title'],
            'price' => (float) $this['price'],
            'formatted_price' => '$' . number_format($this['price'], 0),
            'year' => $this['year'],
            'is_sponsored' => true,
            
            'brand' => $this->when($this['brand'], [
                'id' => $this['brand']['id'] ?? null,
                'name' => $this['brand']['name'] ?? null,
            ]),
            
            'car_model' => $this->when($this['car_model'], [
                'id' => $this['car_model']['id'] ?? null,
                'name' => $this['car_model']['name'] ?? null,
            ]),
            
            'primary_image' => $this->when(isset($this['media']) && count($this['media']) > 0, function () {
                $media = collect($this['media'])->where('sort_order', 1)->first() ?? collect($this['media'])->first();
                return [
                    'id' => $media['id'] ?? null,
                    'url' => $media['file_path'] ? asset('storage/' . $media['file_path']) : null,
                    'alt_text' => $media['alt_text'] ?? null,
                ];
            }),
            
            'created_at' => $this['created_at'] ?? null,
        ];
    }
}
