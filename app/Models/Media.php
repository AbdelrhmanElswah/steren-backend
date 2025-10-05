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
        return $this->getFileFromS3($this->path_file_name);
    }

    protected static function boot()
    {
        parent::boot();

    }
}
