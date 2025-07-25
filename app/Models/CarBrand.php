<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarBrand extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function userPreferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'logo');
    }
}

