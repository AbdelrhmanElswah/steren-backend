<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DealerStudio extends Model
{
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'name',
        'description',
        'address',
        'city_id',
        'phone',
        'email',
        'website',
        'logo_url',
        'cover_image_url',
        'is_active',
        'is_verified',
        'verification_status',
        'verified_at',
        'verified_by',
        'settings',
        'social_links',
        'business_hours',
        'amenities',
        'capacity',
        'specialties',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'settings' => 'array',
        'social_links' => 'array',
        'business_hours' => 'array',
        'amenities' => 'array',
        'specialties' => 'array',
    ];

    /**
     * Get the dealer that owns this studio
     */
    public function dealer()
    {
        return $this->belongsTo(User::class, 'dealer_id');
    }

    /**
     * Get the city where this studio is located
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get car listings for this studio
     */
    public function carListings()
    {
        return $this->hasMany(CarListing::class, 'user_id', 'dealer_id');
    }

    /**
     * Get active car listings for this studio
     */
    public function activeCarListings()
    {
        return $this->carListings()->where('status', 'active');
    }

    /**
     * Get featured car listings for this studio
     */
    public function featuredCarListings()
    {
        return $this->carListings()->where('is_featured', true);
    }

    /**
     * Get top picks car listings for this studio
     */
    public function topPicksCarListings()
    {
        return $this->carListings()
            ->where('is_featured', true)
            ->orWhere('is_sponsored', true)
            ->orderBy('featured_at', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get "You May Also Like" car listings for this studio
     */
    public function youMayAlsoLikeCarListings($excludeId = null)
    {
        $query = $this->carListings()
            ->where('status', 'active')
            ->orderBy('created_at', 'desc');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    /**
     * Get the user who verified this studio
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope to get only active studios
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only verified studios
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get studios by dealer
     */
    public function scopeByDealer($query, $dealerId)
    {
        return $query->where('dealer_id', $dealerId);
    }

    /**
     * Scope to get studios by city
     */
    public function scopeByCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    /**
     * Check if studio is verified
     */
    public function isVerified(): bool
    {
        return $this->is_verified && $this->verification_status === 'verified';
    }

    /**
     * Get verification status label
     */
    public function getVerificationStatusLabelAttribute()
    {
        return match ($this->verification_status) {
            'pending' => 'Pending Verification',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
            default => 'Unknown',
        };
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city?->name,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get business hours formatted
     */
    public function getFormattedBusinessHoursAttribute()
    {
        if (!$this->business_hours) {
            return null;
        }

        $formatted = [];
        foreach ($this->business_hours as $day => $hours) {
            if ($hours['is_open']) {
                $formatted[] = ucfirst($day) . ': ' . $hours['open'] . ' - ' . $hours['close'];
            } else {
                $formatted[] = ucfirst($day) . ': Closed';
            }
        }

        return $formatted;
    }
}
