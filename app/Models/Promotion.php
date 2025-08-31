<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image_url',
        'call_to_action_text',
        'call_to_action_link',
        'is_active',
        'display_order',
        'type',
        'target_audience',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_audience' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('starts_at')
                          ->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>=', now());
                    });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('created_at', 'desc');
    }
}
