<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarfaxReportResource extends JsonResource
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
            'report_number' => $this->report_number,
            'report_date' => $this->report_date,
            'status' => $this->status,
            'vin' => $this->vin,
            'accident_count' => $this->accident_count,
            'owner_count' => $this->owner_count,
            'has_clean_title' => $this->has_clean_title,
            'has_service_history' => $this->has_service_history,
            'notes' => $this->notes,
            'report_url' => $this->report_url,
            'report_data' => $this->report_data,
            'car_listing' => [
                'id' => $this->carListing->id,
                'title' => $this->carListing->full_title,
                'vin' => $this->carListing->vin,
            ]
        ];
    }
}
