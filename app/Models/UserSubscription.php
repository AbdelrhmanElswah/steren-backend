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
        'grace_period_days',
        'grace_period_ends_at',
        'is_in_grace_period',
        'cancelled_at',
        'payment_details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_period_days' => 'integer',
        'grace_period_ends_at' => 'datetime',
        'is_in_grace_period' => 'boolean',
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
     * Includes grace period check
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
        // During grace period, subscription is still considered active
        if ($this->expires_at->isFuture()) {
            return true; // Not expired yet
        }
        
        // Check if in grace period
        return $this->isInGracePeriod();
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

    /**
     * Check if subscription is in grace period
     * Grace period: 15 days after expiration where user can still use plan benefits
     */
    public function isInGracePeriod(): bool
    {
        // If subscription hasn't expired yet, not in grace period
        if (!$this->expires_at || $this->expires_at->isFuture()) {
            return false;
        }

        // Check if grace period has been calculated
        if (!$this->grace_period_ends_at) {
            // Calculate it on the fly
            $gracePeriodDays = $this->grace_period_days ?? 15;
            $gracePeriodEnds = $this->expires_at->copy()->addDays($gracePeriodDays);
            
            // Update the record
            $this->update([
                'grace_period_ends_at' => $gracePeriodEnds,
                'is_in_grace_period' => true,
            ]);
            
            $this->refresh();
        }

        // Check if we're still within grace period
        return now()->lessThan($this->grace_period_ends_at);
    }

    /**
     * Check if grace period has ended
     */
    public function hasGracePeriodEnded(): bool
    {
        if (!$this->grace_period_ends_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->grace_period_ends_at);
    }

    /**
     * Get days remaining in grace period
     */
    public function getGracePeriodDaysRemainingAttribute()
    {
        if (!$this->isInGracePeriod()) {
            return 0;
        }

        return now()->diffInDays($this->grace_period_ends_at, false);
    }

    /**
     * Mark as grace period ended
     */
    public function endGracePeriod()
    {
        $this->update([
            'is_in_grace_period' => false,
            'status' => 'expired',
        ]);

        return $this;
    }
}
