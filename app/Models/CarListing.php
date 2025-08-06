<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'mileage',
        'origin_country',
        'location_id',
        'car_brand_id',
        'car_body_type_id',
        'studio_id',
        'is_sponsored',
        'views',
        'car_model_id',
    ];

    public function brand() {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function bodyType() {
        return $this->belongsTo(BodyType::class, 'car_body_type_id');
    }

    public function location() {
        return $this->belongsTo(Location::class);
    }

    public function studio() {
        return $this->belongsTo(Studio::class);
    }

    public function media() {
        return $this->morphMany(Media::class, 'modelable');
    }

    public function badges() {
        return $this->belongsToMany(ListingBadge::class, 'car_listing_badge');
    }

    public function carModel() {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }
}

