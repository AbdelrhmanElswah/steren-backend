<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CarListingViewsTrack extends Model
{
    protected $table = 'car_listing_views_track';

    protected $fillable = [
        'car_listing_id',
        'ip_address',
        'view_date',
    ];

    protected $casts = [
        'view_date' => 'date',
    ];

    /**
     * Get the car listing that owns this view track
     */
    public function carListing(): BelongsTo
    {
        return $this->belongsTo(CarListing::class);
    }

    /**
     * Check if a view from this IP for this listing exists within the last N hours
     * 
     * @param int $carListingId
     * @param string $ipAddress
     * @param int $hours
     * @return bool
     */
    public static function hasRecentView(int $carListingId, string $ipAddress, int $hours = 2): bool
    {
        $cutoffTime = Carbon::now()->subHours($hours);
        
        return static::where('car_listing_id', $carListingId)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', $cutoffTime)
            ->exists();
    }

    /**
     * Create a new view track record
     * 
     * @param int $carListingId
     * @param string $ipAddress
     * @return static
     */
    public static function createViewTrack(int $carListingId, string $ipAddress): self
    {
        return static::create([
            'car_listing_id' => $carListingId,
            'ip_address' => $ipAddress,
            'view_date' => Carbon::now()->toDateString(),
        ]);
    }
}
