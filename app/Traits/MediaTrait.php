<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

trait MediaTrait
{
   
    protected function getMediaDisk(): string
    {
        return 'public';
    }

  
    public function getTemporaryUrl(?string $path, int $expirationMinutes = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            $disk = Storage::disk($this->getMediaDisk());
            
            if (!$disk->exists($path)) {
                return null;
            }

            if (method_exists($disk, 'temporaryUrl')) {
                try {
                    return $disk->temporaryUrl(
                        $path,
                        Carbon::now()->addMinutes($expirationMinutes)
                    );
                } catch (\RuntimeException $e) {
                    return $disk->url($path);
                }
            }

            return $disk->url($path);
        } catch (\Exception $e) {
            \Log::error('MediaTrait: Error generating temporary URL', [
                'path' => $path,
                'disk' => $this->getMediaDisk(),
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
                'url' => $media->media_link, 
                'file_path' => $media->path_file_name,
                'file_name' => $media->path_file_name,
                'file_size' => $media->file_size ?? null,
                'mime_type' => $media->file_type,
                'info' => $media->info,
                'media_link' => $this->getTemporaryUrl($media->path_file_name, $expirationMinutes),
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
            'url' => $media->media_link,
            'file_path' => $media->path_file_name,
            'file_name' => $media->path_file_name,
            'file_size' => $media->file_size ?? null,
            'mime_type' => $media->file_type,
            'info' => $media->info,
            'media_link' => $this->getTemporaryUrl($media->path_file_name, $expirationMinutes),
            'created_at' => $media->created_at,
            'updated_at' => $media->updated_at,
        ];
    }
}
