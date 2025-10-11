<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'odoo_id',
        'name',
        'description',
        'price',
        'currency',
        'currency_symbol',
        'billing_cycle',
        'features',
        'max_listings',
        'max_photos',
        'subscriber_count',
        'is_active',
        'is_popular',
        'is_featured',
        'is_whatsapp_phone',
        'is_badge',
        'is_search',
        'is_similar_cars',
        'is_dedicated_account_rep',
        'is_analytics',
        'is_top_picks',
        'is_priority_support',
        'is_you_may_also_like',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'max_listings' => 'integer',
        'max_photos' => 'integer',
        'subscriber_count' => 'integer',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'is_featured' => 'boolean',
        'is_whatsapp_phone' => 'boolean',
        'is_badge' => 'boolean',
        'is_search' => 'boolean',
        'is_similar_cars' => 'boolean',
        'is_dedicated_account_rep' => 'boolean',
        'is_analytics' => 'boolean',
        'is_top_picks' => 'boolean',
        'is_priority_support' => 'boolean',
        'is_you_may_also_like' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get users subscribed to this plan
     */
    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get active subscriptions
     */
    public function activeSubscriptions()
    {
        return $this->userSubscriptions()->where('status', 'active');
    }

    /**
     * Scope to get only active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get popular plans
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return $this->currency . ' ' . number_format($this->price, 2);
    }

    /**
     * Get price per month (for yearly plans)
     */
    public function getPricePerMonthAttribute()
    {
        if ($this->billing_cycle === 'yearly') {
            return $this->price / 12;
        }
        return $this->price;
    }

    /**
     * Get formatted price per month
     */
    public function getFormattedPricePerMonthAttribute()
    {
        return $this->currency . ' ' . number_format($this->price_per_month, 2);
    }

    /**
     * Get subscription duration in days based on billing cycle
     * Reads from database billing_cycle column
     */
    public function getDurationInDays(): int
    {
        return match($this->billing_cycle) {
            'monthly' => 30,
            'yearly' => 365,
            default => 30, // fallback to monthly
        };
    }

    /**
     * Calculate expiration date from start date based on billing cycle
     * Reads from database billing_cycle column
     */
    public function calculateExpirationDate(\DateTime|string $startDate = 'now'): \Carbon\Carbon
    {
        $start = $startDate === 'now' ? \Carbon\Carbon::now() : \Carbon\Carbon::parse($startDate);
        
        return match($this->billing_cycle) {
            'monthly' => $start->addMonth(),
            'yearly' => $start->addYear(),
            default => $start->addMonth(), // fallback to monthly
        };
    }

    /**
     * Get billing cycle display name
     */
    public function getBillingCycleDisplayAttribute(): string
    {
        return match($this->billing_cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            default => ucfirst($this->billing_cycle),
        };
    }

    /**
     * Check if plan is yearly
     */
    public function isYearly(): bool
    {
        return $this->billing_cycle === 'yearly';
    }

    /**
     * Check if plan is monthly
     */
    public function isMonthly(): bool
    {
        return $this->billing_cycle === 'monthly';
    }
}
