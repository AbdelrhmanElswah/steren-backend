<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'type',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get statuses by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get active statuses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get ordered statuses
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get statuses for dealer applications
     */
    public static function dealerApplicationStatuses()
    {
        return static::ofType('dealer_application')->active()->ordered()->get();
    }

    /**
     * Get statuses for car listings
     */
    public static function carListingStatuses()
    {
        return static::ofType('car_listing')->active()->ordered()->get();
    }

    /**
     * Get statuses for users
     */
    public static function userStatuses()
    {
        return static::ofType('user')->active()->ordered()->get();
    }

    /**
     * Get general statuses
     */
    public static function generalStatuses()
    {
        return static::ofType('general')->active()->ordered()->get();
    }

    /**
     * Find status by name and type
     */
    public static function findByNameAndType(string $name, string $type = 'general')
    {
        return static::where('name', $name)->where('type', $type)->first();
    }

    /**
     * Get status badge color
     */
    public function getBadgeColorAttribute()
    {
        return $this->color;
    }
}