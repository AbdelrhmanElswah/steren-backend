<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationType extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'icon',
        'color',
        'is_active',
        'default_template',
        'channels',
        'sort_order'
    ];

    protected $casts = [
        'default_template' => 'array',
        'channels' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Get notifications for this type
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Scope to get active types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find type by name
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Find or create type by name
     */
    public static function findOrCreateByName(
        string $name,
        string $displayName,
        ?string $description = null
    ): self {
        return static::firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }
}
