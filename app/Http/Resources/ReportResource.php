<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
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
            'reason' => $this->reason,
            'reason_label' => $this->reason_label,
            'message' => $this->message,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_confirmed' => $this->is_confirmed,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'reportable_type' => $this->reportable_type,
            'reportable_id' => $this->reportable_id,
        ];
    }
}
