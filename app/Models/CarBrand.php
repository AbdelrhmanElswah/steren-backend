<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarBrand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['odoo_id', 'name'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute()
    {
        if ($this->media) {
            return $this->media->url ?? null;
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

