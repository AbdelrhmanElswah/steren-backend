<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchSuggestionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'brand' => $this->when($this->resource['brand'], function () {
                return new BrandSearchResource($this->resource['brand']);
            }),
            'models' => ModelSearchResource::collection($this->resource['models']),
            'total_listings' => $this->resource['total_listings'] ?? 0,
        ];
    }
}
