<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "deleting" event (soft delete).
     * Delete associated profile picture media record (cascade delete).
     * MediaObserver will handle S3 file deletion.
     * 
     * Note: We only delete profile_picture media, not 'main' media if it exists.
     */
    public function deleting(User $user): void
    {
        try {
            // Load profile picture media relationship if not already loaded
            if (!$user->relationLoaded('profilePictureMedia')) {
                $user->load('profilePictureMedia');
            }

            // Delete profile picture media record if it exists
            if ($user->profilePictureMedia) {
                $user->profilePictureMedia->delete();
            }

            Log::info('User profile picture media deleted (cascade)', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete User profile picture media during cascade delete', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the User "forceDeleting" event (hard delete).
     * Delete associated profile picture media record (cascade delete).
     * MediaObserver will handle S3 file deletion.
     */
    public function forceDeleting(User $user): void
    {
        try {
            // Load profile picture media relationship if not already loaded
            if (!$user->relationLoaded('profilePictureMedia')) {
                $user->load('profilePictureMedia');
            }

            // Force delete profile picture media record if it exists
            if ($user->profilePictureMedia) {
                $user->profilePictureMedia->forceDelete();
            }

            Log::info('User profile picture media force deleted (cascade)', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to force delete User profile picture media during cascade delete', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

