<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carousel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'odoo_id',
        'title',
        'description',
        'button_text',
        'start_date',
        'end_date',
        'is_active',
        'sort_order',
        'priority',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the carousel's media (image)
     */
    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'main');
    }

    /**
     * Get all media for this carousel
     */
    public function medias()
    {
        return $this->morphMany(Media::class, 'modelable');
    }

    /**
     * Scope for active carousels
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for carousels within date range
     */
    public function scopeWithinDateRange($query)
    {
        $now = now()->toDateString();
        return $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
    }

    /**
     * Scope for ordered carousels
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');
    }

    /**
     * Get formatted start date
     */
    public function getFormattedStartDateAttribute()
    {
        return $this->start_date ? $this->start_date->format('d/m/Y') : null;
    }

    /**
     * Get formatted end date
     */
    public function getFormattedEndDateAttribute()
    {
        return $this->end_date ? $this->end_date->format('d/m/Y') : null;
    }

    /**
     * Check if carousel is currently active (within date range and active flag)
     */
    public function isCurrentlyActive()
    {
        $now = now()->toDateString();
        return $this->is_active && 
               $this->start_date <= $now && 
               $this->end_date >= $now;
    }
}
