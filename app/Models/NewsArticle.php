<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'excerpt',
        'image_url',
        'category',
        'author',
        'source_url',
        'status',
        'is_featured',
        'view_count',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Get the media for this news article
     */
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Scope to get only published articles
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope to get featured articles
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get articles by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get latest articles
     */
    public function scopeLatest($query, $limit = 10)
    {
        return $query->orderBy('published_at', 'desc')->limit($limit);
    }

    /**
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    /**
     * Get the main image URL
     */
    public function getMainImageUrlAttribute()
    {
        return $this->image_url ?: $this->media->first()?->image_url;
    }

    /**
     * Get formatted published date
     */
    public function getFormattedPublishedDateAttribute()
    {
        return $this->published_at?->format('M d, Y');
    }
}
