<?php

namespace App\Http\Resources\Api\Odoo;

use App\Traits\ResourceMediaTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizedAgencyResource extends JsonResource
{
    use ResourceMediaTrait;

    public function toArray(Request $request): array
    {
        $useTemporaryUrl = $this->shouldUseTemporaryUrls($request);
        $expirationMinutes = $this->getExpirationMinutes($request);

        // Fetch studio_id based on partner_id
        $studioId = null;
        if ($this->partner_id) {
            $user = \App\Models\User::where('partner_id', $this->partner_id)->first();
            if ($user) {
                $studio = \App\Models\DealerStudio::where('dealer_id', $user->id)->first();
                if ($studio) {
                    $studioId = $studio->id;
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'partner_id' => $this->partner_id,
            'studio_id' => $studioId,
            'media' => $this->when(
                $this->relationLoaded('media') && $this->media,
                $this->getStandardMedia($this->media, $request, $useTemporaryUrl, $expirationMinutes)
            ),
        ];
    }
}

