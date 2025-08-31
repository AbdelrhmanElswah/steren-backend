<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'icon',
        'is_standard',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_standard' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get car listings that have this feature
     */
    public function carListings()
    {
        return $this->belongsToMany(CarListing::class, 'car_listing_feature')
                    ->withPivot('is_included', 'notes')
                    ->withTimestamps();
    }

    /**
     * Scope to get only active features
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get features by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get standard features
     */
    public function scopeStandard($query)
    {
        return $query->where('is_standard', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->category));
    }

    /**
     * Get formatted category for display
     */
    public function getFormattedCategoryAttribute()
    {
        $labels = [
            'interior' => 'Interior',
            'exterior' => 'Exterior',
            'safety' => 'Safety',
            'comfort_convenience' => 'Comfort & Convenience',
            'infotainment' => 'Infotainment',
            'manufacture_warranty' => 'Manufacture Warranty',
            'performance' => 'Performance',
            'technology' => 'Technology',
        ];

        return $labels[$this->category] ?? $this->category_label;
    }
}
