<?php

namespace App\Observers;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MediaObserver
{
    /**
     * Handle the Media "deleting" event.
     * Delete the S3 file before the media record is deleted.
     */
    public function deleting(Media $media): void
    {
        try {
            if ($media->path_file_name) {
                // Check if file exists before attempting to delete
                if (Storage::disk('s3')->exists($media->path_file_name)) {
                    Storage::disk('s3')->delete($media->path_file_name);
                    
                    Log::info('Media file deleted from S3', [
                        'media_id' => $media->id,
                        'path' => $media->path_file_name,
                        'modelable_type' => $media->modelable_type,
                        'modelable_id' => $media->modelable_id,
                    ]);
                } else {
                    Log::warning('Media file not found in S3 during deletion', [
                        'media_id' => $media->id,
                        'path' => $media->path_file_name,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't prevent deletion of the media record
            // This ensures database consistency even if S3 deletion fails
            Log::error('Failed to delete media file from S3', [
                'media_id' => $media->id,
                'path' => $media->path_file_name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

