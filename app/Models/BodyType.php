<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BodyType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function userPreferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function listings() {
        return $this->hasMany(CarListing::class);
    }
}

