<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProcessing extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'wallet_id',
        'payment_method',
        'payment_gateway',
        'amount',
        'currency',
        'status',
        'payment_type',
        'payment_data',
        'otp_code',
        'otp_expires_at',
        'otp_verified_at',
        'reference_id',
        'failure_reason',
        'gateway_response',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_data' => 'array',
        'gateway_response' => 'array',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the user that owns this payment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet associated with this payment
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get processing payments
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to get completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get cancelled payments
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if payment is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is processing
     */
    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is failed
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment is cancelled
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if OTP is valid
     */
    public function isOtpValid()
    {
        return $this->otp_code && 
               $this->otp_expires_at && 
               $this->otp_expires_at->isFuture() &&
               !$this->otp_verified_at;
    }

    /**
     * Check if OTP is expired
     */
    public function isOtpExpired()
    {
        return $this->otp_expires_at && $this->otp_expires_at->isPast();
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Mark payment as processing
     */
    public function markAsProcessing()
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed($reason = null)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Mark payment as cancelled
     */
    public function markAsCancelled()
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp($otpCode)
    {
        if ($this->otp_code === $otpCode && $this->isOtpValid()) {
            $this->update(['otp_verified_at' => now()]);
            return true;
        }
        return false;
    }

    /**
     * Generate new OTP
     */
    public function generateOtp()
    {
        $otpCode = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->update([
            'otp_code' => $otpCode,
            'otp_expires_at' => now()->addMinutes(10),
            'otp_verified_at' => null,
        ]);
        return $otpCode;
    }

    /**
     * Get payment type label
     */
    public function getPaymentTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->payment_type));
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    /**
     * Generate transaction ID
     */
    public static function generateTransactionId()
    {
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())->count() + 1;
        return "TXN-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
