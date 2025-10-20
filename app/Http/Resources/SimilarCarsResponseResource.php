<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimilarCarsResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'similar_cars' => SimilarCarsResource::collection($this->resource['similar_cars']),
            'total' => $this->resource['total'],
            
            // Reference car information (when available)
            'reference_car' => $this->when($this->resource['reference_car'], function () {
                return [
                    'id' => $this->resource['reference_car']->id,
                    'title' => $this->resource['reference_car']->title,
                    'brand' => $this->resource['reference_car']->brand?->name,
                    'model' => $this->resource['reference_car']->carModel?->name,
                    'price' => $this->resource['reference_car']->price,
                    'year' => $this->resource['reference_car']->year,
                ];
            }),
            
            // Similarity factors (when available)
            'similarity_factors' => $this->resource['similarity_factors'] ?? null,
            
            // Brand information (when searching by brand)
            'brand' => $this->when(isset($this->resource['brand']) && $this->resource['brand'], function () {
                return [
                    'id' => $this->resource['brand']->id,
                    'name' => $this->resource['brand']->name,
                    'logo' => $this->resource['brand']->media ? asset('storage/' . $this->resource['brand']->media->path_file_name) : null,
                ];
            }),
            
            // Model information (when searching by model)
            'model' => $this->when(isset($this->resource['model']) && $this->resource['model'], function () {
                return [
                    'id' => $this->resource['model']->id,
                    'name' => $this->resource['model']->name,
                ];
            }),
            
            // Body type information (when searching by body type)
            'body_type' => $this->when(isset($this->resource['body_type']) && $this->resource['body_type'], function () {
                return [
                    'id' => $this->resource['body_type']->id,
                    'name' => $this->resource['body_type']->name,
                    'icon' => $this->resource['body_type']->media ? asset('storage/' . $this->resource['body_type']->media->path_file_name) : null,
                ];
            }),
            
            // Price range (when searching by price)
            'price_range' => $this->resource['price_range'] ?? null,
            
            // Year range (when searching by year)
            'year_range' => $this->resource['year_range'] ?? null,
            
            // Search criteria (when using advanced search)
            'criteria' => $this->resource['criteria'] ?? null,
        ];
    }
}
