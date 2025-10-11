<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevicePermission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'location',
        'general_notifications',
        'camera',
        'photos',
        'last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'location' => 'boolean',
        'general_notifications' => 'boolean',
        'camera' => 'boolean',
        'photos' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the device permissions.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all permissions as an array
     */
    public function getAllPermissions(): array
    {
        return [
            'location' => $this->location,
            'general_notifications' => $this->general_notifications,
            'camera' => $this->camera,
            'photos' => $this->photos,
        ];
    }

    /**
     * Update a specific permission
     */
    public function updatePermission(string $permission, bool $enabled): bool
    {
        if (!in_array($permission, ['location', 'general_notifications', 'camera', 'photos'])) {
            return false;
        }

        $this->update([
            $permission => $enabled,
            'last_updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Get permission status for a specific permission
     */
    public function getPermissionStatus(string $permission): ?bool
    {
        if (!in_array($permission, ['location', 'general_notifications', 'camera', 'photos'])) {
            return null;
        }

        return $this->$permission;
    }

    /**
     * Check if user has enabled any permissions
     */
    public function hasAnyPermissionsEnabled(): bool
    {
        return $this->location || 
               $this->general_notifications || 
               $this->camera || 
               $this->photos;
    }

    /**
     * Get enabled permissions count
     */
    public function getEnabledPermissionsCount(): int
    {
        $count = 0;
        if ($this->location) $count++;
        if ($this->general_notifications) $count++;
        if ($this->camera) $count++;
        if ($this->photos) $count++;
        
        return $count;
    }

    /**
     * Get permission status for display purposes
     */
    public function getPermissionStatusText(string $permission): string
    {
        $status = $this->getPermissionStatus($permission);
        
        if ($status === null) {
            return 'Invalid Permission';
        }
        
        return $status ? 'ON' : 'OFF';
    }
}
