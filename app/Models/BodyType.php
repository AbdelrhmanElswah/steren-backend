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

<<<<<<< Updated upstream
    public function medias()
    {
        return $this->morphMany(Media::class, 'modelable');
=======
<<<<<<< HEAD
    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'icon');
=======
    public function medias()
    {
        return $this->morphMany(Media::class, 'modelable');
>>>>>>> f1e647cb0f702afed587be491f9e4c378f5d7c75
>>>>>>> Stashed changes
    }
}

