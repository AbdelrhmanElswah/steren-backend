<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarListingAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_listing_id',
        'metric_type',
        'metric_value',
        'date',
        'additional_data',
    ];

    protected $casts = [
        'date' => 'date',
        'metric_value' => 'integer',
        'additional_data' => 'array',
    ];

    /**
     * Get the car listing that owns this analytics record
     */
    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    /**
     * Scope to get analytics by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope to get analytics by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get analytics for last N days
     */
    public function scopeLastDays($query, $days)
    {
        return $query->where('date', '>=', now()->subDays($days));
    }

    /**
     * Get total views for a car listing
     */
    public static function getTotalViews($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'views')
            ->sum('metric_value');
    }

    /**
     * Get total shares for a car listing
     */
    public static function getTotalShares($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'shares')
            ->sum('metric_value');
    }

    /**
     * Get total call taps for a car listing
     */
    public static function getTotalCallTaps($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'call_taps')
            ->sum('metric_value');
    }

    /**
     * Get total WhatsApp clicks for a car listing
     */
    public static function getTotalWhatsAppClicks($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'whatsapp_clicks')
            ->sum('metric_value');
    }

    /**
     * Get total SMS clicks for a car listing
     */
    public static function getTotalSmsClicks($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'sms')
            ->sum('metric_value');
    }

    /**
     * Get total email clicks for a car listing
     */
    public static function getTotalEmailClicks($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'email')
            ->sum('metric_value');
    }

    /**
     * Get total watchlist saves for a car listing
     */
    public static function getTotalWatchlistSaves($carListingId)
    {
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'watchlist_saves')
            ->sum('metric_value');
    }

    /**
     * Get analytics summary for a car listing
     */
    public static function getAnalyticsSummary($carListingId)
    {
        return [
            'views' => static::getTotalViews($carListingId),
            'shares' => static::getTotalShares($carListingId),
            'call_taps' => static::getTotalCallTaps($carListingId),
            'whatsapp_clicks' => static::getTotalWhatsAppClicks($carListingId),
            'sms' => static::getTotalSmsClicks($carListingId),
            'email' => static::getTotalEmailClicks($carListingId),
            'watchlist_saves' => static::getTotalWatchlistSaves($carListingId),
        ];
    }

    /**
     * Get days active for a car listing
     */
    public static function getDaysActive($carListingId)
    {
        $firstView = static::where('car_listing_id', $carListingId)
            ->where('metric_type', 'views')
            ->orderBy('date', 'asc')
            ->first();

        if (!$firstView) {
            return 0;
        }

        return now()->diffInDays($firstView->date) + 1;
    }

    /**
     * Increment a metric for a car listing
     */
    public static function incrementMetric($carListingId, $metricType, $additionalData = null)
    {
        $today = now()->toDateString();
        
        $analytics = static::firstOrCreate([
            'car_listing_id' => $carListingId,
            'metric_type' => $metricType,
            'date' => $today,
        ], [
            'metric_value' => 0,
            'additional_data' => $additionalData,
        ]);

        $analytics->increment('metric_value');
        
        return $analytics;
    }
}
