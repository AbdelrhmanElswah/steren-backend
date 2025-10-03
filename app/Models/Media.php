<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'media', 'path_file_name', 'file_type', 'info',
    ];

    protected $appends = ['media_link'];

    public function modelable()
    {
        return $this->morphTo();
    }

    public function getMediaLinkAttribute()
    {
        return asset('storage/' . $this->path_file_name);
    }
}

