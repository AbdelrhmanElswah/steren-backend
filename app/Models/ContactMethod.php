<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'color',
        'is_active',
        'display_order',
        'settings',
        'action_type',
        'action_url',
        'requires_phone',
        'requires_email',
        'instructions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'requires_phone' => 'boolean',
        'requires_email' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Get action URL with parameters
     */
    public function getActionUrl($phone = null, $email = null, $message = null)
    {
        $url = $this->action_url;
        
        if ($phone) {
            $url = str_replace('{phone}', $phone, $url);
        }
        
        if ($email) {
            $url = str_replace('{email}', $email, $url);
        }
        
        if ($message) {
            $url = str_replace('{message}', urlencode($message), $url);
        }
        
        return $url;
    }

    /**
     * Check if this method can be used with given contact info
     */
    public function canUse($phone = null, $email = null)
    {
        if ($this->requires_phone && !$phone) {
            return false;
        }
        
        if ($this->requires_email && !$email) {
            return false;
        }
        
        return true;
    }
}
