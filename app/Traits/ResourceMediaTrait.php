<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ResourceMediaTrait
{
    /**
     * Get media data for resources
     *
     * @param mixed $media
     * @param Request $request
     * @param bool $useTemporaryUrl
     * @param int $expirationMinutes
     * @return array|null
     */
    public function getMediaData($media, Request $request, bool $useTemporaryUrl = true, int $expirationMinutes = 60): ?array
    {
        if (!$media) {
            return null;
        }

        return [
            'id' => $media->id,
            'info' => $media->info,
            'file_type' => $media->file_type,
            'url' => $useTemporaryUrl 
                ? $media->getS3TemporaryUrl($media->path_file_name, $expirationMinutes)
                : $media->getS3PublicUrl($media->path_file_name),
            'public_url' => $media->getS3PublicUrl($media->path_file_name),
            'file_size' => $media->file_size,
            'mime_type' => $media->mime_type,
            'exists' => $media->file_exists,
            'created_at' => $media->created_at?->toISOString(),
            'updated_at' => $media->updated_at?->toISOString(),
        ];
    }

    /**
     * Get multiple media data for resources
     *
     * @param mixed $medias
     * @param Request $request
     * @param bool $useTemporaryUrl
     * @param int $expirationMinutes
     * @return array
     */
    public function getMultipleMediaData($medias, Request $request, bool $useTemporaryUrl = true, int $expirationMinutes = 60): array
    {
        if (!$medias || $medias->isEmpty()) {
            return [];
        }

        return $medias->map(function ($media) use ($request, $useTemporaryUrl, $expirationMinutes) {
            return $this->getMediaData($media, $request, $useTemporaryUrl, $expirationMinutes);
        })->filter()->values()->toArray();
    }

    /**
     * Get single media URL (for backward compatibility)
     *
     * @param mixed $media
     * @param Request $request
     * @param bool $useTemporaryUrl
     * @param int $expirationMinutes
     * @return string|null
     */
    public function getMediaUrl($media, Request $request, bool $useTemporaryUrl = true, int $expirationMinutes = 60): ?string
    {
        if (!$media) {
            return null;
        }

        return $useTemporaryUrl 
            ? $media->getS3TemporaryUrl($media->path_file_name, $expirationMinutes)
            : $media->getS3PublicUrl($media->path_file_name);
    }

    /**
     * Get media URL with fallback
     *
     * @param mixed $media
     * @param Request $request
     * @param string $fallbackUrl
     * @param bool $useTemporaryUrl
     * @param int $expirationMinutes
     * @return string
     */
    public function getMediaUrlWithFallback($media, Request $request, string $fallbackUrl = '', bool $useTemporaryUrl = true, int $expirationMinutes = 60): string
    {
        $url = $this->getMediaUrl($media, $request, $useTemporaryUrl, $expirationMinutes);
        return $url ?: $fallbackUrl;
    }

    /**
     * Check if media should use temporary URLs based on request
     *
     * @param Request $request
     * @return bool
     */
    public function shouldUseTemporaryUrls(Request $request): bool
    {
        // You can customize this logic based on your needs
        // For example, use temporary URLs for authenticated users only
        return $request->user() !== null;
    }

    /**
     * Get expiration minutes based on request
     *
     * @param Request $request
     * @return int
     */
    public function getExpirationMinutes(Request $request): int
    {
        // You can customize this logic based on your needs
        // For example, longer expiration for authenticated users
        if ($request->user()) {
            return 120; // 2 hours for authenticated users
        }
        
        return 60; // 1 hour for anonymous users
    }
}
