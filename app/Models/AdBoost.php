<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdBoost extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_listing_id',
        'user_id',
        'boost_type',
        'description',
        'cost',
        'currency',
        'duration_days',
        'started_at',
        'expires_at',
        'status',
        'boost_features',
        'payment_details',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'duration_days' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'boost_features' => 'array',
        'payment_details' => 'array',
    ];

    /**
     * Get the car listing that owns this boost
     */
    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    /**
     * Get the user that owns this boost
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active boosts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get expired boosts
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to get cancelled boosts
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if boost is active
     */
    public function isActive()
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    /**
     * Check if boost is expired
     */
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute()
    {
        return $this->currency . ' ' . number_format($this->cost, 2);
    }

    /**
     * Get days remaining
     */
    public function getDaysRemainingAttribute()
    {
        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Get formatted expiry date
     */
    public function getFormattedExpiryDateAttribute()
    {
        return $this->expires_at->format('M d, Y');
    }

    /**
     * Cancel boost
     */
    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark boost as expired
     */
    public function markAsExpired()
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Get boost type label
     */
    public function getBoostTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->boost_type));
    }
}
