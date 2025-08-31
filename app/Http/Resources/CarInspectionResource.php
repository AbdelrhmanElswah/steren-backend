<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarInspectionResource extends JsonResource
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
            'inspection_number' => $this->inspection_number,
            'inspection_date' => $this->inspection_date,
            'status' => $this->status,
            'score' => $this->score,
            'notes' => $this->notes,
            'report_url' => $this->report_url,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at,
            'inspector' => $this->inspector ? [
                'id' => $this->inspector->id,
                'name' => $this->inspector->full_name,
            ] : null,
            'inspection_results' => $this->inspection_results,
            'car_listing' => [
                'id' => $this->carListing->id,
                'title' => $this->carListing->full_title,
                'vin' => $this->carListing->vin,
            ]
        ];
    }
}
