<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_ids',
        'title',
        'body',
        'data',
        'options',
        'notification_type_id',
        'scheduled_at',
        'sent_at',
        'status',
        'result',
        'error_message',
        'attempts'
    ];

    protected $casts = [
        'user_ids' => 'array',
        'data' => 'array',
        'options' => 'array',
        'result' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime'
    ];

    /**
     * Get the notification type
     */
    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                    ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope for processing notifications
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for cancelled notifications
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if notification is ready to be sent
     */
    public function isReadyToSend(): bool
    {
        return $this->status === 'pending' && 
               $this->scheduled_at <= now();
    }

    /**
     * Check if notification can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Cancel the notification
     */
    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        return $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): bool
    {
        return $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1
        ]);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(array $result = []): bool
    {
        return $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'result' => $result
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage = null, array $result = []): bool
    {
        return $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'result' => $result
        ]);
    }

    /**
     * Get user count
     */
    public function getUserCountAttribute(): int
    {
        return count($this->user_ids ?? []);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => '#f59e0b',
            'processing' => '#3b82f6',
            'sent' => '#10b981',
            'failed' => '#ef4444',
            'cancelled' => '#6b7280',
            default => '#6b7280'
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'sent' => 'Sent',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }
}