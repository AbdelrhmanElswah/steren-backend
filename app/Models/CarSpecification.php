<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarSpecification extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_listing_id',
        'specification_type',
        'name',
        'value',
        'unit',
        'description',
        'is_highlighted',
        'sort_order',
    ];

    protected $casts = [
        'is_highlighted' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the car listing that owns this specification
     */
    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    /**
     * Scope to get specifications by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('specification_type', $type);
    }

    /**
     * Scope to get highlighted specifications
     */
    public function scopeHighlighted($query)
    {
        return $query->where('is_highlighted', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get formatted value with unit
     */
    public function getFormattedValueAttribute()
    {
        if ($this->unit) {
            return $this->value . ' ' . $this->unit;
        }
        return $this->value;
    }

    /**
     * Get specification type label
     */
    public function getTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->specification_type));
    }
}
