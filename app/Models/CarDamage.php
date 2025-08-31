<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarDamage extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_listing_id',
        'car_part_id',
        'status',
        'description',
        'repair_cost',
        'repair_date',
        'repair_shop',
        'damage_details',
        'image_url',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'repair_cost' => 'decimal:2',
        'repair_date' => 'date',
        'damage_details' => 'array',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    public function carPart()
    {
        return $this->belongsTo(CarPart::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeClean($query)
    {
        return $query->where('status', 'clean');
    }

    public function scopeRepaired($query)
    {
        return $query->where('status', 'repaired');
    }

    public function scopeDamaged($query)
    {
        return $query->where('status', 'damaged');
    }

    public function scopeNeedsRepair($query)
    {
        return $query->where('status', 'needs_repair');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function verify($verifiedBy = null)
    {
        $this->update([
            'is_verified' => true,
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ]);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'clean' => 'green',
            'repaired' => 'blue',
            'damaged' => 'red',
            'needs_repair' => 'orange',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }
}
