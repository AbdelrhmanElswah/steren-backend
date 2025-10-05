<?php

namespace App\Http\Resources\Api\Odoo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ResourceMediaTrait;

class BodyTypeResource extends JsonResource
{
    use ResourceMediaTrait;

    public function toArray(Request $request): array
    {
        $useTemporaryUrl = $this->shouldUseTemporaryUrls($request);
        $expirationMinutes = $this->getExpirationMinutes($request);

        return [
            'id' => $this->id,
            'odoo_id' => $this->odoo_id,
            'name' => $this->name,
            'icon_url' => $this->when(
                $this->relationLoaded('media') && $this->media, 
                $this->getMediaUrl($this->media, $request, $useTemporaryUrl, $expirationMinutes)
            ),
            'media' => $this->when(
                $this->relationLoaded('media') && $this->media,
                $this->getMediaData($this->media, $request, $useTemporaryUrl, $expirationMinutes)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}