<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SafetyTip extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'icon',
        'is_active',
        'display_order',
        'category',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('created_at', 'desc');
    }
}
