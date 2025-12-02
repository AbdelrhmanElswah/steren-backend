<?php

namespace App\Observers;

use App\Models\Carousel;
use Illuminate\Support\Facades\Log;

class CarouselObserver
{
    /**
     * Handle the Carousel "deleting" event (soft delete).
     * Delete all associated media records (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function deleting(Carousel $carousel): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$carousel->relationLoaded('medias')) {
                $carousel->load('medias');
            }

            // Delete all media records
            // This will trigger MediaObserver::deleting() for each media record
            $carousel->medias()->delete();

            Log::info('Carousel media deleted (cascade)', [
                'carousel_id' => $carousel->id,
                'media_count' => $carousel->medias->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete Carousel media during cascade delete', [
                'carousel_id' => $carousel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Carousel "forceDeleting" event (hard delete).
     * Delete all associated media records (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function forceDeleting(Carousel $carousel): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$carousel->relationLoaded('medias')) {
                $carousel->load('medias');
            }

            // Force delete all media records
            $carousel->medias()->forceDelete();

            Log::info('Carousel media force deleted (cascade)', [
                'carousel_id' => $carousel->id,
                'media_count' => $carousel->medias->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to force delete Carousel media during cascade delete', [
                'carousel_id' => $carousel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

