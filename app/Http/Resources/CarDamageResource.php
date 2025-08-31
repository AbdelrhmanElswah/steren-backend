<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarDamageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'part_name' => $this->carPart->name,
            'part_id' => $this->car_part_id,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'description' => $this->description,
            'repair_cost' => $this->repair_cost,
            'repair_date' => $this->repair_date,
            'repair_shop' => $this->repair_shop,
            'image_url' => $this->image_url,
            'is_verified' => $this->is_verified,
            'damage_details' => $this->damage_details,
        ];
    }
}
