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
        // Get language from request
        $lang = $request->query('lang');
        if (!in_array($lang, ['ar', 'kr'])) {
            $lang = null;
        }
        
        // Get localized fields
        $title = $lang ? ($this->{'title_' . $lang} ?: $this->title) : $this->title;
        $description = $lang ? ($this->{'description_' . $lang} ?: $this->description) : $this->description;
        $buttonText = $lang ? ($this->{'button_text_' . $lang} ?: $this->button_text) : $this->button_text;
        
        return [
            'id' => $this->odoo_id, // Return Odoo ID as 'id' to match API response
            'title' => $title,
            'description' => $description,
            'button_text' => $buttonText,
            'link_url' => $this->link_url,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'media' => $this->when($this->media, [
                'media_link' => $this->media?->media_link
            ]), // Return media object like Brand API
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
