<?php

namespace App\Observers;

use App\Models\CarSubmission;
use Illuminate\Support\Facades\Log;

class CarSubmissionObserver
{
    /**
     * Handle the CarSubmission "deleting" event (soft delete).
     * Delete all associated media records (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function deleting(CarSubmission $carSubmission): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$carSubmission->relationLoaded('media')) {
                $carSubmission->load('media');
            }

            // Delete all media records
            // This will trigger MediaObserver::deleting() for each media record
            $carSubmission->media()->delete();

            Log::info('CarSubmission media deleted (cascade)', [
                'car_submission_id' => $carSubmission->id,
                'media_count' => $carSubmission->media->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete CarSubmission media during cascade delete', [
                'car_submission_id' => $carSubmission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the CarSubmission "forceDeleting" event (hard delete).
     * Delete all associated media records (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function forceDeleting(CarSubmission $carSubmission): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$carSubmission->relationLoaded('media')) {
                $carSubmission->load('media');
            }

            // Force delete all media records
            $carSubmission->media()->forceDelete();

            Log::info('CarSubmission media force deleted (cascade)', [
                'car_submission_id' => $carSubmission->id,
                'media_count' => $carSubmission->media->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to force delete CarSubmission media during cascade delete', [
                'car_submission_id' => $carSubmission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

