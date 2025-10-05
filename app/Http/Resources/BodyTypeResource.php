<?php

namespace App\Http\Resources;

use App\Traits\MediaTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BodyTypeResource extends JsonResource
{
    use MediaTrait;

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
            'id' => $this->id,
            'name' => $this->name,
            'odoo_id' => $this->odoo_id,
            'icon_url' => $iconUrl,
            'media' => $this->when($this->relationLoaded('media') && $this->media, 
                $this->getMainMediaWithTemporaryUrl($this->media)
            ),
            'medias' => $this->when($this->relationLoaded('medias') && $this->medias, 
                $this->getMediasWithTemporaryUrls($this->medias)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
