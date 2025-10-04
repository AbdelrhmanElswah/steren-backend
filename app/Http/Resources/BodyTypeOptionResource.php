<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BodyTypeOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $iconUrl = null;
        if ($this->relationLoaded('medias') && $this->medias->isNotEmpty()) {
            $iconUrl = $this->medias->first()->media_link;
        }

        return [
            'value' => $this->id,
            'label' => $this->name,
            'icon_url' => $iconUrl,
        ];
    }
}
