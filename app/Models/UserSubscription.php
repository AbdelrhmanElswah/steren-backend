<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'started_at',
        'expires_at',
        'cancelled_at',
        'payment_details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_details' => 'array',
    ];

    /**
     * Get the user that owns this subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Scope to get active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to get cancelled subscriptions
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if subscription is active
     * Real-time validation based on expires_at date
     */
    public function isActive()
    {
        // Check status first
        if ($this->status !== 'active') {
            return false;
        }
        
        // If no expiry date, subscription is active indefinitely
        if ($this->expires_at === null) {
            return true;
        }
        
        // Check if subscription has expired based on expires_at date
        return $this->expires_at->isFuture();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Cancel subscription
     */
    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Get days remaining
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Get formatted expiry date
     */
    public function getFormattedExpiryDateAttribute()
    {
        if (!$this->expires_at) {
            return 'Never expires';
        }

        return $this->expires_at->format('M d, Y');
    }
}
