<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'price',
        'currency',
        'features',
        'visibility_benefits',
        'priority_order',
        'is_active',
        'is_default'
    ];

    protected $casts = [
        'features' => 'array',
        'visibility_benefits' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get all car listings using this promotion
     */
    public function carListings()
    {
        return $this->hasMany(CarListing::class);
    }

    /**
     * Scope to get only active promotions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get sponsored promotions
     */
    public function scopeSponsored($query)
    {
        return $query->where('type', 'sponsored');
    }

    /**
     * Scope to get free promotions
     */
    public function scopeFree($query)
    {
        return $query->where('type', 'free');
    }

    /**
     * Scope to get default promotion
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Check if this is a sponsored promotion
     */
    public function isSponsored(): bool
    {
        return $this->type === 'sponsored';
    }

    /**
     * Check if this is a free promotion
     */
    public function isFree(): bool
    {
        return $this->type === 'free';
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price == 0) {
            return 'Free';
        }
        
        return number_format($this->price, 0) . ' ' . $this->currency;
    }

    /**
     * Get price in cents (for payment processing)
     */
    public function getPriceInCentsAttribute(): int
    {
        return (int) ($this->price * 100);
    }

    /**
     * Check if promotion has specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Check if promotion has specific visibility benefit
     */
    public function hasVisibilityBenefit(string $benefit): bool
    {
        return $this->visibility_benefits[$benefit] ?? false;
    }

    /**
     * Get all active promotions ordered by priority
     */
    public static function getActivePromotions()
    {
        return static::active()->orderBy('priority_order', 'desc')->get();
    }

    /**
     * Get default promotion
     */
    public static function getDefault()
    {
        return static::default()->first();
    }

    /**
     * Get sponsored promotion
     */
    public static function getSponsored()
    {
        return static::sponsored()->first();
    }

    /**
     * Get free promotion
     */
    public static function getFree()
    {
        return static::free()->first();
    }

    /**
     * Calculate promotion duration in days
     * For sponsored plans, this might be different from free plans
     */
    public function getDurationInDays(): int
    {
        return match($this->type) {
            'sponsored' => 30, // Sponsored listings last 30 days
            'free' => 365, // Free listings last 1 year
            default => 30,
        };
    }

    /**
     * Calculate when promotion expires from a given date
     */
    public function calculateExpirationDate(\DateTime|string $startDate = 'now'): \Carbon\Carbon
    {
        $start = $startDate === 'now' ? \Carbon\Carbon::now() : \Carbon\Carbon::parse($startDate);
        return $start->addDays($this->getDurationInDays());
    }

    /**
     * Get promotion features as HTML list
     */
    public function getFeaturesAsHtmlAttribute(): string
    {
        if (empty($this->features)) {
            return '';
        }

        $html = '<ul class="promotion-features">';
        foreach ($this->features as $feature) {
            $html .= '<li>' . e($feature) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Get visibility benefits summary
     */
    public function getVisibilitySummaryAttribute(): array
    {
        $benefits = [];
        
        if ($this->hasVisibilityBenefit('priority_placement')) {
            $benefits[] = 'Priority placement in search results';
        }
        
        if ($this->hasVisibilityBenefit('top_picks_featured')) {
            $benefits[] = 'Featured in top picks section';
        }
        
        if ($this->hasVisibilityBenefit('social_media_promotion')) {
            $benefits[] = 'Social media promotion';
        }
        
        if ($this->hasVisibilityBenefit('premium_badge')) {
            $benefits[] = 'Premium badge display';
        }
        
        if ($this->hasVisibilityBenefit('enhanced_search_ranking')) {
            $benefits[] = 'Enhanced search ranking';
        }
        
        if ($this->hasVisibilityBenefit('free_vin_decoder')) {
            $benefits[] = 'Free VIN decoder';
        }
        
        if ($this->hasVisibilityBenefit('new_arrival_badge')) {
            $benefits[] = 'New arrival badge';
        }
        
        if ($this->hasVisibilityBenefit('standard_visibility')) {
            $benefits[] = 'Standard visibility';
        }
        
        if ($this->hasVisibilityBenefit('similar_cars_showing')) {
            $benefits[] = 'Shows in similar cars';
        }

        return $benefits;
    }
}
