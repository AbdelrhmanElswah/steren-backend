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
        'inspection_number',
        'inspection_date',
        'status',
        'notes',
        'is_verified',
        'verified_at',
        'right_side',
        'left_side',
        'roof',
        'front',
        'back',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'right_side' => 'boolean',
        'left_side' => 'boolean',
        'roof' => 'boolean',
        'front' => 'boolean',
        'back' => 'boolean',
    ];

    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function verify()
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Get the documents for this car inspection
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get inspection report document
     */
    public function inspectionReportDocument()
    {
        return $this->documents()->where('category', 'inspection_report')->first();
    }

    /**
     * Get all verified documents
     */
    public function verifiedDocuments()
    {
        return $this->documents()->where('is_verified', true);
    }

    /**
     * Get all pending documents
     */
    public function pendingDocuments()
    {
        return $this->documents()->where('is_verified', false);
    }
}
