<?php

namespace App\Http\Resources;

use App\Traits\MediaTrait;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    use MediaTrait;

    /**
     * Get media with temporary URL
     *
     * @param int $expirationMinutes
     * @return array|null
     */
    protected function getMediaWithTemporaryUrl(int $expirationMinutes = 60): ?array
    {
        if (!$this->relationLoaded('media') || !$this->media) {
            return null;
        }

        return $this->getMainMediaWithTemporaryUrl($this->media, $expirationMinutes);
    }

    /**
     * Get all medias with temporary URLs
     *
     * @param int $expirationMinutes
     * @return array
     */
    protected function getMediasWithTemporaryUrls(int $expirationMinutes = 60): array
    {
        if (!$this->relationLoaded('medias') || !$this->medias) {
            return [];
        }

        return $this->getMediasWithTemporaryUrls($this->medias, $expirationMinutes);
    }
}
