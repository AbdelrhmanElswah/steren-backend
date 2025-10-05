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
