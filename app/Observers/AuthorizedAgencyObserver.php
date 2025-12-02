<?php

namespace App\Observers;

use App\Models\AuthorizedAgency;
use Illuminate\Support\Facades\Log;

class AuthorizedAgencyObserver
{
    /**
     * Handle the AuthorizedAgency "deleting" event (soft delete).
     * Delete associated media record (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function deleting(AuthorizedAgency $authorizedAgency): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$authorizedAgency->relationLoaded('media')) {
                $authorizedAgency->load('media');
            }

            // Delete media record if it exists
            if ($authorizedAgency->media) {
                $authorizedAgency->media->delete();
            }

            Log::info('AuthorizedAgency media deleted (cascade)', [
                'authorized_agency_id' => $authorizedAgency->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete AuthorizedAgency media during cascade delete', [
                'authorized_agency_id' => $authorizedAgency->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the AuthorizedAgency "forceDeleting" event (hard delete).
     * Delete associated media record (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function forceDeleting(AuthorizedAgency $authorizedAgency): void
    {
        try {
            // Load media relationship if not already loaded
            if (!$authorizedAgency->relationLoaded('media')) {
                $authorizedAgency->load('media');
            }

            // Force delete media record if it exists
            if ($authorizedAgency->media) {
                $authorizedAgency->media->forceDelete();
            }

            Log::info('AuthorizedAgency media force deleted (cascade)', [
                'authorized_agency_id' => $authorizedAgency->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to force delete AuthorizedAgency media during cascade delete', [
                'authorized_agency_id' => $authorizedAgency->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

