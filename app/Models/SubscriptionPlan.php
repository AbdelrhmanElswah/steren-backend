<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'billing_cycle',
        'features',
        'max_listings',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'max_listings' => 'integer',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
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
}
