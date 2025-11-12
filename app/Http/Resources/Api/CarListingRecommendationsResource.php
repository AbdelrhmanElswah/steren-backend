<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarListingRecommendationsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $listings = CarListingResource::collection($this['listings']);

        return [
            'listings' => $listings->toArray($request),
            'meta' => $this['meta'] ?? [],
        ];
    }
}

