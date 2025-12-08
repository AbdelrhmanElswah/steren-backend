<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;

class CarBrand extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = ['odoo_id', 'name', 'name_ar', 'name_kr'];

    protected $appends = ['logo'];

    public function getLogoAttribute()
    {
        if ($this->relationLoaded('media') && $this->media) {
            return [
                'id' => $this->media->id,
                'url' => $this->media->media_temporary_url ?? $this->media->url,
                'file_path' => $this->media->file_path ?? $this->media->path_file_name,
                'file_type' => $this->media->file_type,
                'file_size' => $this->media->file_size,
                'mime_type' => $this->media->mime_type,
            ];
        }
        return null;
    }

    public function userPreferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'logo');
    }

    public function listings() {
        return $this->hasMany(CarListing::class, 'car_brand_id');
    }

    public function carModels() {
        return $this->hasMany(CarModel::class, 'car_brand_id');
    }

    public function carSubmissions() {
        return $this->hasMany(CarSubmission::class, 'car_brand_id');
    }
}

