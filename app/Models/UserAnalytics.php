<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metric_type',
        'metric_value',
        'date',
        'additional_data',
    ];

    protected $casts = [
        'date' => 'date',
        'additional_data' => 'array',
    ];

    /**
     * Get the user that owns this analytics record
     */
    public function user()
    {
        return $this->belongsTo(User::class);
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
     * Get total views for user
     */
    public static function getTotalViews($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'views')
            ->sum('metric_value');
    }

    /**
     * Get total favorites for user
     */
    public static function getTotalFavorites($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'favorites')
            ->sum('metric_value');
    }

    /**
     * Get total inquiries for user
     */
    public static function getTotalInquiries($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'inquiries')
            ->sum('metric_value');
    }

    /**
     * Get total sales for user
     */
    public static function getTotalSales($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'sales')
            ->sum('metric_value');
    }

    /**
     * Get total shares for user
     */
    public static function getTotalShares($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'shares')
            ->sum('metric_value');
    }

    /**
     * Get total call taps for user
     */
    public static function getTotalCallTaps($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'call_taps')
            ->sum('metric_value');
    }

    /**
     * Get total WhatsApp clicks for user
     */
    public static function getTotalWhatsAppClicks($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'whatsapp_clicks')
            ->sum('metric_value');
    }

    /**
     * Get total SMS clicks for user
     */
    public static function getTotalSmsClicks($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'sms')
            ->sum('metric_value');
    }

    /**
     * Get total email clicks for user
     */
    public static function getTotalEmailClicks($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'email')
            ->sum('metric_value');
    }

    /**
     * Get total watchlist saves for user
     */
    public static function getTotalWatchlistSaves($userId)
    {
        return static::where('user_id', $userId)
            ->where('metric_type', 'watchlist_saves')
            ->sum('metric_value');
    }

    /**
     * Get analytics summary for user
     */
    public static function getAnalyticsSummary($userId)
    {
        return [
            'total_views' => static::getTotalViews($userId),
            'total_favorites' => static::getTotalFavorites($userId),
            'total_inquiries' => static::getTotalInquiries($userId),
            'total_sales' => static::getTotalSales($userId),
            'total_shares' => static::getTotalShares($userId),
            'total_call_taps' => static::getTotalCallTaps($userId),
            'total_whatsapp_clicks' => static::getTotalWhatsAppClicks($userId),
            'total_sms' => static::getTotalSmsClicks($userId),
            'total_email' => static::getTotalEmailClicks($userId),
            'total_watchlist_saves' => static::getTotalWatchlistSaves($userId),
        ];
    }

    /**
     * Increment a metric for a user
     */
    public static function incrementMetric($userId, $metricType, $additionalData = null)
    {
        $today = now()->toDateString();
        
        $analytics = static::firstOrCreate([
            'user_id' => $userId,
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
