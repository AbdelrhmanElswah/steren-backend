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

    /**
     * Get trend for a metric (increase/decrease/no change)
     */
    public static function getMetricTrend($carListingId, $metricType, $period = 'this_month')
    {
        $currentPeriod = static::getMetricValueForPeriod($carListingId, $metricType, $period);
        $previousPeriod = static::getMetricValueForPreviousPeriod($carListingId, $metricType, $period);
        
        if ($previousPeriod == 0) {
            return $currentPeriod > 0 ? 'up' : 'neutral';
        }
        
        $change = (($currentPeriod - $previousPeriod) / $previousPeriod) * 100;
        
        if ($change > 5) {
            return 'up';
        } elseif ($change < -5) {
            return 'down';
        } else {
            return 'neutral';
        }
    }

    /**
     * Get metric value for a specific period
     */
    public static function getMetricValueForPeriod($carListingId, $metricType, $period)
    {
        $startDate = static::getStartDate($period);
        
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', $metricType)
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('date', '>=', $startDate);
            })
            ->sum('metric_value');
    }

    /**
     * Get metric value for the previous period
     */
    public static function getMetricValueForPreviousPeriod($carListingId, $metricType, $period)
    {
        $currentStartDate = static::getStartDate($period);
        $currentEndDate = now()->toDateString();
        
        if (!$currentStartDate) {
            return 0; // For 'all_time', there's no previous period
        }
        
        $periodDuration = now()->parse($currentStartDate)->diffInDays(now()->parse($currentEndDate));
        $previousEndDate = now()->parse($currentStartDate)->subDay()->toDateString();
        $previousStartDate = now()->parse($previousEndDate)->subDays($periodDuration)->toDateString();
        
        return static::where('car_listing_id', $carListingId)
            ->where('metric_type', $metricType)
            ->whereBetween('date', [$previousStartDate, $previousEndDate])
            ->sum('metric_value');
    }

    /**
     * Get analytics with trends
     */
    public static function getAnalyticsWithTrends($carListingId, $period = 'this_month')
    {
        $metrics = ['views', 'shares', 'call_taps', 'whatsapp_clicks', 'sms', 'email', 'watchlist_saves'];
        $analytics = [];
        
        foreach ($metrics as $metric) {
            $analytics[$metric] = [
                'value' => static::getMetricValueForPeriod($carListingId, $metric, $period),
                'trend' => static::getMetricTrend($carListingId, $metric, $period),
            ];
        }
        
        return $analytics;
    }

    /**
     * Get start date for a period
     */
    private static function getStartDate($period)
    {
        return match ($period) {
            'last_7_days' => now()->subDays(7)->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'all_time' => null,
            default => now()->startOfMonth()->toDateString(),
        };
    }
}
