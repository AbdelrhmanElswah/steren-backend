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
        'car_condition_id',
        'mileage_type_id',
        'import_type_id',
        'fuel_type_id',
        'transmission_id',
        'exterior_color_id',
        'drive_type_id',
        'warranty_status_id',
        'cylinder_id',
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

    public function carCondition() {
        return $this->belongsTo(CarCondition::class);
    }

    public function mileageType() {
        return $this->belongsTo(MileageType::class);
    }

    public function importType() {
        return $this->belongsTo(ImportType::class);
    }

    public function fuelType() {
        return $this->belongsTo(FuelType::class);
    }

    public function transmission() {
        return $this->belongsTo(Transmission::class);
    }

    public function exteriorColor() {
        return $this->belongsTo(ExteriorColor::class);
    }

    public function driveType() {
        return $this->belongsTo(DriveType::class);
    }

    public function warrantyStatus() {
        return $this->belongsTo(WarrantyStatus::class);
    }

    public function cylinder() {
        return $this->belongsTo(CylinderOption::class);
    }

}

