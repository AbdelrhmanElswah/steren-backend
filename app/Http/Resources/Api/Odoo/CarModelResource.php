<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'name' => $this->name,
            'car_brand_id' => $this->car_brand_id,
            'car_brand' => $this->when(
                $this->relationLoaded('brand') && $this->brand,
                [
                    'id' => $this->brand->id,
                    'odoo_id' => $this->brand->odoo_id,
                    'name' => $this->brand->name,
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}