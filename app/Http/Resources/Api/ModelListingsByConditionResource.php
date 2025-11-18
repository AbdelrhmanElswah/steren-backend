<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModelListingsByConditionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'brand' => $this['brand'] ?? [],
            'studio' => $this['studio'] ?? [],
            'new' => [
                'count' => $this['new']['count'] ?? 0,
                'listings' => $this['new']['listings'] ?? [],
            ],
            'used' => [
                'count' => $this['used']['count'] ?? 0,
                'listings' => $this['used']['listings'] ?? [],
            ],
        ];
    }
}

