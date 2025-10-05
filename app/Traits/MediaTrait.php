<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

trait MediaTrait
{
  
    public function getTemporaryUrl(?string $path, int $expirationMinutes = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            if (!Storage::disk('s3')->exists($path)) {
                return null;
            }

            return Storage::disk('s3')->temporaryUrl(
                $path,
                Carbon::now()->addMinutes($expirationMinutes)
            );
        } catch (\Exception $e) {
            \Log::error('MediaTrait: Error generating temporary URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getMediasWithTemporaryUrls($medias, int $expirationMinutes = 60): array
    {
        if (!$medias || $medias->isEmpty()) {
            return [];
        }

        return $medias->map(function ($media) use ($expirationMinutes) {
            return [
                'id' => $media->id,
                'file_path' => $media->file_path,
                'file_name' => $media->file_name,
                'file_size' => $media->file_size,
                'mime_type' => $media->mime_type,
                'info' => $media->info,
                'temporary_url' => $this->getTemporaryUrl($media->file_path, $expirationMinutes),
                'created_at' => $media->created_at,
                'updated_at' => $media->updated_at,
            ];
        })->toArray();
    }


    public function getMainMediaWithTemporaryUrl($media, int $expirationMinutes = 60): ?array
    {
        if (!$media) {
            return null;
        }

        return [
            'id' => $media->id,
            'file_path' => $media->file_path,
            'file_name' => $media->file_name,
            'file_size' => $media->file_size,
            'mime_type' => $media->mime_type,
            'info' => $media->info,
            'temporary_url' => $this->getTemporaryUrl($media->file_path, $expirationMinutes),
            'created_at' => $media->created_at,
            'updated_at' => $media->updated_at,
        ];
    }
}
