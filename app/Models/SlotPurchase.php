<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Slot Purchase Model
 * 
 * Tracks purchases of additional listing slots.
 * Purchased slots persist even when user has active subscription.
 */
class SlotPurchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'slots_purchased',
        'amount_paid',
        'currency',
        'currency_symbol',
        'payment_method',
        'transaction_id',
        'payment_status',
        'payment_details',
        'notes',
        'purchased_at',
    ];

    protected $casts = [
        'slots_purchased' => 'integer',
        'amount_paid' => 'decimal:2',
        'payment_details' => 'array',
        'purchased_at' => 'datetime',
    ];

    /**
     * Get the user that made this purchase
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get completed purchases
     */
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    /**
     * Scope to get pending purchases
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope to get failed purchases
     */
    public function scopeFailed($query)
    {
        return $query->where('payment_status', 'failed');
    }

    /**
     * Scope to get refunded purchases
     */
    public function scopeRefunded($query)
    {
        return $query->where('payment_status', 'refunded');
    }

    /**
     * Mark purchase as completed and add slots to user
     */
    public function markAsCompleted()
    {
        if ($this->payment_status === 'completed') {
            return $this; // Already completed
        }

        $this->payment_status = 'completed';
        $this->save();

        // Get or create user slot record
        $userSlot = UserSlot::firstOrCreate(
            ['user_id' => $this->user_id],
            [
                'total_slots' => 1,
                'consumed_slots' => 0,
                'available_slots' => 1,
                'purchased_slots' => 0,
                'has_monthly_free_slot' => true,
                'last_free_slot_reset_at' => now(),
                'next_free_slot_reset_at' => now()->addMonth()->startOfMonth(),
            ]
        );

        // Add purchased slots
        $userSlot->addPurchasedSlots($this->slots_purchased);

        return $this;
    }

    /**
     * Mark purchase as failed
     */
    public function markAsFailed()
    {
        $this->payment_status = 'failed';
        $this->save();

        return $this;
    }

    /**
     * Mark purchase as refunded and remove slots from user
     */
    public function markAsRefunded()
    {
        if ($this->payment_status === 'refunded') {
            return $this; // Already refunded
        }

        $this->payment_status = 'refunded';
        $this->save();

        // Remove slots from user
        $userSlot = UserSlot::where('user_id', $this->user_id)->first();
        if ($userSlot) {
            $userSlot->removePurchasedSlots($this->slots_purchased);
        }

        return $this;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return ($this->currency_symbol ?? $this->currency) . ' ' . number_format($this->amount_paid, 2);
    }

    /**
     * Get price per slot
     */
    public function getPricePerSlotAttribute()
    {
        if ($this->slots_purchased == 0) {
            return 0;
        }

        return $this->amount_paid / $this->slots_purchased;
    }

    /**
     * Get formatted price per slot
     */
    public function getFormattedPricePerSlotAttribute()
    {
        return ($this->currency_symbol ?? $this->currency) . ' ' . number_format($this->price_per_slot, 2);
    }

    /**
     * Check if purchase is completed
     */
    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Check if purchase is pending
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if purchase is failed
     */
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Check if purchase is refunded
     */
    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }
}

