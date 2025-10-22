<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VinDecoderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vin' => $this->vin,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'trim' => $this->trim,
            'body_type' => $this->body_type,
            'engine_type' => $this->engine_type,
            'transmission' => $this->transmission,
            'drive_type' => $this->drive_type,
            'fuel_type' => $this->fuel_type,
            'exterior_color' => $this->exterior_color,
            'interior_color' => $this->interior_color,
            'doors' => $this->doors,
            'cylinders' => $this->cylinders,
            'manufacturer' => $this->manufacturer,
            'plant' => $this->plant,
            'additional_data' => $this->additional_data,
            'last_updated' => $this->last_updated,
        ];
    }
}
