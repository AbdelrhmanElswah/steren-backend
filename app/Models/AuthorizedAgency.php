<?php

namespace App\Models;

use App\Traits\ExcludesSoftDeleted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Media;

class AuthorizedAgency extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = [
        'odoo_id',
        'name',
        'name_ar',
        'name_kr',
        'partner_id',
    ];

    protected $appends = [
        'media_file',
    ];

    public function getMediaFileAttribute(): ?array
    {
        if ($this->relationLoaded('media') && $this->media) {
            return [
                'id' => $this->media->id,
                'url' => $this->media->media_temporary_url ?? $this->media->media_link,
                'file_path' => $this->media->path_file_name ?? $this->media->media,
                'file_type' => $this->media->file_type,
                'file_size' => $this->media->file_size,
                'mime_type' => $this->media->mime_type,
            ];
        }

        return null;
    }

    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'authorized_agency_media');
    }

    public function models()
    {
        return $this->hasMany(AuthorizedAgencyModel::class);
    }
}

