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
            'new' => [
                'count' => $this['new']['count'] ?? 0,
                'listings' => CarListingResource::collection(
                    collect($this['new']['listings'] ?? [])
                )->toArray($request),
            ],
            'used' => [
                'count' => $this['used']['count'] ?? 0,
                'listings' => CarListingResource::collection(
                    collect($this['used']['listings'] ?? [])
                )->toArray($request),
            ],
        ];
    }
}

