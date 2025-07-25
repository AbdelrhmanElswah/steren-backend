<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'car_brand_id', 'body_type_id', 'city_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function carBrand()
    {
        return $this->belongsTo(CarBrand::class);
    }

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}

