<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarInspection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'odoo_id',
        'car_listing_id',
        'inspector_id',
        'inspection_number',
        'inspection_date',
        'status',
        'inspection_results',
        'notes',
        'report_url',
        'score',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'inspection_results' => 'array',
        'score' => 'decimal:1',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByScore($query, $minScore = null, $maxScore = null)
    {
        if ($minScore !== null) {
            $query->where('score', '>=', $minScore);
        }
        if ($maxScore !== null) {
            $query->where('score', '<=', $maxScore);
        }
        return $query;
    }

    public function verify()
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}
