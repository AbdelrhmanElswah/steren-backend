<?php

namespace App\Observers;

use App\Models\CarBrand;
use Illuminate\Support\Facades\Log;

class CarBrandObserver
{
    /**
     * Handle the CarBrand "deleting" event (soft delete).
     * Delete associated media record (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function deleting(CarBrand $carBrand): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$carBrand->relationLoaded('media')) {
                $carBrand->load('media');
            }

            // Delete media record if it exists
            if ($carBrand->media) {
                $carBrand->media->delete();
            }

            Log::info('CarBrand media deleted (cascade)', [
                'car_brand_id' => $carBrand->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete CarBrand media during cascade delete', [
                'car_brand_id' => $carBrand->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the CarBrand "forceDeleting" event (hard delete).
     * Delete associated media record (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function forceDeleting(CarBrand $carBrand): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$carBrand->relationLoaded('media')) {
                $carBrand->load('media');
            }

            // Force delete media record if it exists
            if ($carBrand->media) {
                $carBrand->media->forceDelete();
            }

            Log::info('CarBrand media force deleted (cascade)', [
                'car_brand_id' => $carBrand->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to force delete CarBrand media during cascade delete', [
                'car_brand_id' => $carBrand->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

