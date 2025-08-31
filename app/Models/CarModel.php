<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarModel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'car_brand_id'];

    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function listings()
    {
        return $this->hasMany(CarListing::class, 'car_model_id');
    }

    public function trims()
    {
        return $this->hasMany(CarTrim::class);
    }
}

