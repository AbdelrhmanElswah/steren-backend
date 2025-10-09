<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ResourceMediaTrait;

class CarouselResource extends JsonResource
{
    use ResourceMediaTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $useTemporaryUrl = $this->shouldUseTemporaryUrls($request);
        $expirationMinutes = $this->getExpirationMinutes($request);

        return [
            'id' => $this->odoo_id,
            'title' => $this->title,
            'description' => $this->description,
            'button_text' => $this->button_text,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'is_currently_active' => $this->isCurrentlyActive(),
            'sort_order' => $this->sort_order,
            'media' => $this->when(
                $this->relationLoaded('medias') && $this->medias->isNotEmpty(),
                $this->getStandardMedia($this->medias->first(), $request, $useTemporaryUrl, $expirationMinutes)
            ),
            'image_alt' => $this->medias->first()->alt_text ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
