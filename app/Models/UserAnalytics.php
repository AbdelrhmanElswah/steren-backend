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
     * Get analytics summary for user
     */
    public static function getAnalyticsSummary($userId)
    {
        return [
            'total_views' => static::getTotalViews($userId),
            'total_favorites' => static::getTotalFavorites($userId),
            'total_inquiries' => static::getTotalInquiries($userId),
            'total_sales' => static::getTotalSales($userId),
        ];
    }
}
