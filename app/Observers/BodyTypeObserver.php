<?php

namespace App\Observers;

use App\Models\BodyType;
use Illuminate\Support\Facades\Log;

class BodyTypeObserver
{
    /**
     * Handle the BodyType "deleting" event (hard delete - no soft deletes).
     * Delete all associated media records (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function deleting(BodyType $bodyType): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$bodyType->relationLoaded('medias')) {
                $bodyType->load('medias');
            }

            // Delete all media records
            // This will trigger MediaObserver::deleting() for each media record
            $bodyType->medias()->delete();

            Log::info('BodyType media deleted (cascade)', [
                'body_type_id' => $bodyType->id,
                'media_count' => $bodyType->medias->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete BodyType media during cascade delete', [
                'body_type_id' => $bodyType->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

