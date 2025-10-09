<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ResourceMediaTrait;

class BodyTypeResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'odoo_id' => $this->odoo_id,
            'media' => $this->when(
                $this->relationLoaded('medias') && $this->medias->isNotEmpty(),
                $this->getStandardMedia($this->medias->first(), $request, $useTemporaryUrl, $expirationMinutes)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
