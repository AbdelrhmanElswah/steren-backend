<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'search_query',
        'filters',
        'sort_options',
        'is_saved',
        'notifications_enabled',
        'search_count',
        'last_searched_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'sort_options' => 'array',
        'is_saved' => 'boolean',
        'notifications_enabled' => 'boolean',
        'search_count' => 'integer',
        'last_searched_at' => 'datetime',
    ];

    /**
     * Get the user that owns this search history
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only saved searches
     */
    public function scopeSaved($query)
    {
        return $query->where('is_saved', true);
    }

    /**
     * Scope to get searches with notifications enabled
     */
    public function scopeWithNotifications($query)
    {
        return $query->where('notifications_enabled', true);
    }

    /**
     * Scope to order by last searched
     */
    public function scopeOrderByLastSearched($query)
    {
        return $query->orderBy('last_searched_at', 'desc');
    }

    /**
     * Scope to order by search count
     */
    public function scopeOrderBySearchCount($query)
    {
        return $query->orderBy('search_count', 'desc');
    }

    /**
     * Increment search count and update last searched
     */
    public function incrementSearchCount()
    {
        $this->increment('search_count');
        $this->update(['last_searched_at' => now()]);
    }

    /**
     * Get formatted search count
     */
    public function getFormattedSearchCountAttribute()
    {
        if ($this->search_count >= 1000) {
            return number_format($this->search_count / 1000, 1) . 'K';
        }
        return $this->search_count;
    }

    /**
     * Get filter tags for display
     */
    public function getFilterTagsAttribute()
    {
        if (!$this->filters) {
            return [];
        }

        $tags = [];
        
        // Add color filter
        if (isset($this->filters['exterior_color'])) {
            $color = ExteriorColor::find($this->filters['exterior_color']);
            if ($color) {
                $tags[] = $color->name;
            }
        }

        // Add year filter
        if (isset($this->filters['year'])) {
            $tags[] = $this->filters['year'];
        }

        // Add condition filter
        if (isset($this->filters['condition_type'])) {
            $tags[] = ucfirst($this->filters['condition_type']);
        }

        // Add body type filter
        if (isset($this->filters['body_type'])) {
            $bodyType = BodyType::find($this->filters['body_type']);
            if ($bodyType) {
                $tags[] = $bodyType->name;
            }
        }

        // Add transmission filter
        if (isset($this->filters['transmission'])) {
            $transmission = Transmission::find($this->filters['transmission']);
            if ($transmission) {
                $tags[] = $transmission->name;
            }
        }

        // Add drive type filter
        if (isset($this->filters['drive_type'])) {
            $driveType = DriveType::find($this->filters['drive_type']);
            if ($driveType) {
                $tags[] = $driveType->name;
            }
        }

        return $tags;
    }
}

