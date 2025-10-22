<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;

class Budget extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = [
        'odoo_id',
        'name',
        'value',
        'sequence',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active budgets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sequence
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence', 'asc');
    }

    /**
     * Get formatted value
     */
    public function getFormattedValueAttribute()
    {
        return number_format($this->value, 0);
    }

    /**
     * Get budget range display
     */
    public function getRangeDisplayAttribute()
    {
        return $this->name;
    }
}

