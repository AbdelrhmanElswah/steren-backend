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
}
