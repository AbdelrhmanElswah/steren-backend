<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'message',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'is_confirmed',
        'evidence',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'is_confirmed' => 'boolean',
        'evidence' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }

    public function getReasonLabelAttribute()
    {
        return match($this->reason) {
            'inaccurate_information' => 'Inaccurate Information',
            'offensive_content' => 'Offensive Content',
            'spam' => 'Spam',
            'inappropriate_language' => 'Inappropriate Language',
            'copyright_infringement' => 'Copyright Infringement',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->reason))
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'under_review' => 'Under Review',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
            default => ucfirst(str_replace('_', ' ', $this->status))
        };
    }

    public function review($status, $adminNotes = null, $reviewedBy = null)
    {
        $this->update([
            'status' => $status,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function confirm()
    {
        $this->update(['is_confirmed' => true]);
    }
}
