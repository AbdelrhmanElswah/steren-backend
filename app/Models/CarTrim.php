<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarTrim extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_model_id',
        'name',
        'description',
        'base_price',
        'engine_size',
        'engine_type',
        'horsepower',
        'torque',
        'transmission',
        'cylinders',
        'drive_type',
        'acceleration_0_100',
        'top_speed',
        'seating_capacity',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'horsepower' => 'integer',
        'cylinders' => 'integer',
        'acceleration_0_100' => 'decimal:1',
        'top_speed' => 'integer',
        'seating_capacity' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the car model that owns this trim
     */
    public function carModel()
    {
        return $this->belongsTo(CarModel::class);
    }

    /**
     * Get the car brand through the car model
     */
    public function carBrand()
    {
        return $this->hasOneThrough(CarBrand::class, CarModel::class, 'id', 'id', 'car_model_id', 'car_brand_id');
    }

    /**
     * Scope to get only active trims
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('base_price');
    }

    /**
     * Get the full trim name with model and brand
     */
    public function getFullNameAttribute()
    {
        return $this->carModel->carBrand->name . ' ' . $this->carModel->name . ' ' . $this->name;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->base_price, 0);
    }
}
