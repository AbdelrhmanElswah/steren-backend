<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

trait MediaTrait
{
    /**
     * Get temporary S3 URL for a file
     *
     * @param string $path
     * @param int $expirationMinutes
     * @return string|null
     */
    public function getS3TemporaryUrl(string $path, int $expirationMinutes = 60): ?string
    {
        try {
            if (empty($path)) {
                return null;
            }

            // Check if file exists in S3
            if (!Storage::disk('s3')->exists($path)) {
                Log::warning('MediaTrait: File not found in S3', ['path' => $path]);
                return null;
            }

            // Generate temporary URL
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($expirationMinutes));
        } catch (\Exception $e) {
            Log::error('MediaTrait: Error generating S3 temporary URL', [
                'path' => $path,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get public S3 URL for a file
     *
     * @param string $path
     * @return string|null
     */
    public function getS3PublicUrl(string $path): ?string
    {
        try {
            if (empty($path)) {
                return null;
            }

            // Check if file exists in S3
            if (!Storage::disk('s3')->exists($path)) {
                Log::warning('MediaTrait: File not found in S3', ['path' => $path]);
                return null;
            }

            // Generate public URL
            return Storage::disk('s3')->url($path);
        } catch (\Exception $e) {
            Log::error('MediaTrait: Error generating S3 public URL', [
                'path' => $path,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get media URL with fallback options
     *
     * @param string $path
     * @param bool $useTemporaryUrl
     * @param int $expirationMinutes
     * @return string|null
     */
    public function getMediaUrl(string $path, bool $useTemporaryUrl = true, int $expirationMinutes = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        if ($useTemporaryUrl) {
            return $this->getS3TemporaryUrl($path, $expirationMinutes);
        }

        return $this->getS3PublicUrl($path);
    }

    /**
     * Check if file exists in S3
     *
     * @param string $path
     * @return bool
     */
    public function fileExistsInS3(string $path): bool
    {
        try {
            if (empty($path)) {
                return false;
            }

            return Storage::disk('s3')->exists($path);
        } catch (\Exception $e) {
            Log::error('MediaTrait: Error checking file existence in S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file size from S3
     *
     * @param string $path
     * @return int|null
     */
    public function getS3FileSize(string $path): ?int
    {
        try {
            if (empty($path) || !$this->fileExistsInS3($path)) {
                return null;
            }

            return Storage::disk('s3')->size($path);
        } catch (\Exception $e) {
            Log::error('MediaTrait: Error getting file size from S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get file MIME type from S3
     *
     * @param string $path
     * @return string|null
     */
    public function getS3FileMimeType(string $path): ?string
    {
        try {
            if (empty($path) || !$this->fileExistsInS3($path)) {
                return null;
            }

            return Storage::disk('s3')->mimeType($path);
        } catch (\Exception $e) {
            Log::error('MediaTrait: Error getting file MIME type from S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }
}
