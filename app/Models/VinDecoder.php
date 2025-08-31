<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VinDecoder extends Model
{
    use HasFactory;

    protected $table = 'vin_decoder';

    protected $fillable = [
        'vin',
        'make',
        'model',
        'year',
        'trim',
        'body_type',
        'engine_type',
        'transmission',
        'drive_type',
        'fuel_type',
        'exterior_color',
        'interior_color',
        'doors',
        'cylinders',
        'manufacturer',
        'plant',
        'additional_data',
        'last_updated',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'last_updated' => 'datetime',
    ];

    public function carListing()
    {
        return $this->hasOne(CarListing::class, 'vin', 'vin');
    }

    public function carSubmission()
    {
        return $this->hasOne(CarSubmission::class, 'vin', 'vin');
    }

    public function scopeByVin($query, $vin)
    {
        return $query->where('vin', strtoupper($vin));
    }

    public function scopeByMake($query, $make)
    {
        return $query->where('make', 'like', "%{$make}%");
    }

    public function scopeByModel($query, $model)
    {
        return $query->where('model', 'like', "%{$model}%");
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeByYearRange($query, $fromYear, $toYear)
    {
        return $query->whereBetween('year', [$fromYear, $toYear]);
    }
}
