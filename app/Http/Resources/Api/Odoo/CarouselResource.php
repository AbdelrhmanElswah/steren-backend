<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarouselResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->odoo_id, // Return Odoo ID as 'id' to match API response
            'title' => $this->title,
            'description' => $this->description,
            'button_text' => $this->button_text,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'img' => $this->when($this->media, $this->media?->media_link), // Return image URL as 'img'
            'formatted_start_date' => $this->formatted_start_date,
            'formatted_end_date' => $this->formatted_end_date,
            'is_active' => $this->is_active,
            'is_currently_active' => $this->isCurrentlyActive(),
            'sort_order' => $this->sort_order,
            'image_details' => $this->when($this->media, [
                'id' => $this->media?->id,
                'url' => $this->media?->media_link,
                'alt_text' => $this->media?->alt_text,
                'file_name' => $this->media?->file_name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
