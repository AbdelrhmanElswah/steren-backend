<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\MediaTrait;

class Media extends Model
{
    use HasFactory, MediaTrait;

    protected $fillable = ['modelable_type', 'modelable_id', 'media', 'info', 'path_file_name', 'file_path', 'url', 'file_type', 'file_size', 'mime_type', 'is_primary'];

    protected $appends = ['media_link', 'media_temporary_url', 'file_size', 'mime_type'];

    public function modelable()
    {
        return $this->morphTo();
    }

    /**
     * Get media link (temporary URL)
     */
    public function getMediaLinkAttribute()
    {
        return $this->getS3TemporaryUrl($this->path_file_name);
    }

    /**
     * Get media temporary URL
     */
    public function getMediaTemporaryUrlAttribute()
    {
        return $this->getS3TemporaryUrl($this->path_file_name, 60); // 1 hour expiration
    }

    /**
     * Get file size
     */
    public function getFileSizeAttribute()
    {
        return $this->getS3FileSize($this->path_file_name);
    }

    /**
     * Get MIME type
     */
    public function getMimeTypeAttribute()
    {
        return $this->getS3FileMimeType($this->path_file_name);
    }

    /**
     * Get public URL (non-expiring)
     */
    public function getPublicUrlAttribute()
    {
        return $this->getS3PublicUrl($this->path_file_name);
    }

    /**
     * Check if file exists
     */
    public function getFileExistsAttribute()
    {
        return $this->fileExistsInS3($this->path_file_name);
    }

    protected static function boot()
    {
        parent::boot();
    }
}
