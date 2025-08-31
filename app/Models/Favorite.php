<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = [
        'user_id',
        'car_listing_id',
        'notes',
        'notifications_enabled',
        'price_when_added',
        'viewed_at',
        'view_count',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'price_when_added' => 'decimal:2',
        'viewed_at' => 'datetime',
        'view_count' => 'integer',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function carListing(): BelongsTo
    {
        return $this->belongsTo(CarListing::class);
    }

    /**
     * Scope to get favorites with notifications enabled
     */
    public function scopeWithNotifications($query)
    {
        return $query->where('notifications_enabled', true);
    }

    /**
     * Scope to order by recently added
     */
    public function scopeOrderByRecentlyAdded($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to order by recently viewed
     */
    public function scopeOrderByRecentlyViewed($query)
    {
        return $query->orderBy('viewed_at', 'desc');
    }

    /**
     * Increment view count and update viewed at
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
        $this->update(['viewed_at' => now()]);
    }

    /**
     * Get price change since added to watch list
     */
    public function getPriceChangeAttribute()
    {
        if (!$this->price_when_added || !$this->carListing) {
            return null;
        }

        $currentPrice = $this->carListing->price;
        $change = $currentPrice - $this->price_when_added;
        $percentage = ($change / $this->price_when_added) * 100;

        return [
            'amount' => $change,
            'percentage' => $percentage,
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'same'),
        ];
    }
}
