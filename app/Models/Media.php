<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $fillable = ['modelable_type', 'modelable_id', 'media', 'info', 'path_file_name', 'file_type'];

    protected $appends = ['media_link'];

    public function modelable()
    {
        return $this->morphTo();
    }

    public function getMediaLinkAttribute()
    {
        return $this->getFileFromStorage($this->path_file_name);
    }

    public function getFileFromStorage(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            // For Laravel Cloud, we need to handle storage differently
            // Try to use the storage disk first, then fall back to direct URL
            $disk = \Storage::disk('public');
            
            // Check if we're on Laravel Cloud (production environment)
            if (config('app.env') === 'production' && str_contains(config('app.url'), 'laravel.cloud')) {
                // For Laravel Cloud, use direct URL format
                $baseUrl = config('app.url');
                return $baseUrl . '/storage/' . $path;
            }
            
            // For local development, use the storage disk
            if ($disk->exists($path)) {
                return $disk->url($path);
            }
            
            // Fallback to direct URL if file doesn't exist in storage
            $baseUrl = config('app.url');
            return $baseUrl . '/storage/' . $path;
            
        } catch (\Exception $e) {
            \Log::error('Media: Error getting file URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected static function boot()
    {
        parent::boot();

    }
}
