<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'payment_method',
        'payment_gateway',
        'reference_id',
        'status',
        'description',
        'payment_details',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payment_details' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the wallet that owns this transaction
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope to get credit transactions
     */
    public function scopeCredits($query)
    {
        return $query->where('transaction_type', 'credit');
    }

    /**
     * Scope to get debit transactions
     */
    public function scopeDebits($query)
    {
        return $query->where('transaction_type', 'debit');
    }

    /**
     * Scope to get completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Get formatted balance before
     */
    public function getFormattedBalanceBeforeAttribute()
    {
        return $this->currency . ' ' . number_format($this->balance_before, 2);
    }

    /**
     * Get formatted balance after
     */
    public function getFormattedBalanceAfterAttribute()
    {
        return $this->currency . ' ' . number_format($this->balance_after, 2);
    }

    /**
     * Check if transaction is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is failed
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Mark transaction as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark transaction as failed
     */
    public function markAsFailed()
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Get transaction type label
     */
    public function getTransactionTypeLabelAttribute()
    {
        return ucfirst($this->transaction_type);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }
}
