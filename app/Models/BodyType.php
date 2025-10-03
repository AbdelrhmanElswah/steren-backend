<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BodyType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'odoo_id'];

    public function userPreferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function listings() {
        return $this->hasMany(CarListing::class, 'car_body_type_id');
    }

    public function carSubmissions() {
        return $this->hasMany(CarSubmission::class, 'car_body_type_id');
    }

    public function medias()
    {
        return $this->morphMany(Media::class, 'modelable');
    }
}

