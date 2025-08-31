<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarfaxReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_listing_id',
        'vin',
        'report_number',
        'report_date',
        'status',
        'report_data',
        'report_url',
        'accident_count',
        'owner_count',
        'has_clean_title',
        'has_service_history',
        'notes',
    ];

    protected $casts = [
        'report_date' => 'date',
        'report_data' => 'array',
        'has_clean_title' => 'boolean',
        'has_service_history' => 'boolean',
    ];

    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopeByVin($query, $vin)
    {
        return $query->where('vin', strtoupper($vin));
    }

    public function scopeCleanTitle($query)
    {
        return $query->where('has_clean_title', true);
    }

    public function scopeWithServiceHistory($query)
    {
        return $query->where('has_service_history', true);
    }

    public function scopeByAccidentCount($query, $maxAccidents = 0)
    {
        return $query->where('accident_count', '<=', $maxAccidents);
    }
}
