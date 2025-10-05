<?php

namespace App\Http\Resources\Api\Odoo;

use App\Traits\MediaTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarBrandResource extends JsonResource
{
    use MediaTrait;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'name' => $this->name,
            'logo_url' => $this->when($this->relationLoaded('media') && $this->media, $this->media->media_link),
            'media' => $this->when($this->relationLoaded('media') && $this->media, 
                $this->getMainMediaWithTemporaryUrl($this->media)
            ),
            'medias' => $this->when($this->relationLoaded('medias') && $this->medias, 
                $this->getMediasWithTemporaryUrls($this->medias)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}