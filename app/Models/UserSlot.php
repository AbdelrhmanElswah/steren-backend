<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * User Slot Model
 * 
 * Manages slots for FREE USERS ONLY.
 * Subscribed users use max_listings from their subscription plan.
 */
class UserSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_slots',
        'consumed_slots',
        'available_slots',
        'purchased_slots',
        'has_monthly_free_slot',
        'last_free_slot_reset_at',
        'next_free_slot_reset_at',
    ];

    protected $casts = [
        'total_slots' => 'integer',
        'consumed_slots' => 'integer',
        'available_slots' => 'integer',
        'purchased_slots' => 'integer',
        'has_monthly_free_slot' => 'boolean',
        'last_free_slot_reset_at' => 'datetime',
        'next_free_slot_reset_at' => 'datetime',
    ];

    /**
     * Get the user that owns this slot record
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate and update total slots
     * Total = monthly free slot (1 or 0) + purchased slots
     */
    public function recalculateTotalSlots()
    {
        $freeSlot = $this->has_monthly_free_slot ? 1 : 0;
        
        $this->total_slots = $freeSlot + $this->purchased_slots;
        $this->available_slots = max(0, $this->total_slots - $this->consumed_slots);
        $this->save();
        
        return $this;
    }

    /**
     * Consume a slot when creating a listing
     * Slots stay consumed even if listing is deleted
     */
    public function consumeSlot()
    {
        if ($this->available_slots <= 0) {
            return false;
        }

        // Mark monthly free slot as used if available
        if ($this->has_monthly_free_slot) {
            $this->has_monthly_free_slot = false;
        }

        $this->consumed_slots++;
        $this->recalculateTotalSlots();

        return true;
    }

    /**
     * Check if user can create a listing (has available slots)
     */
    public function canCreateListing(): bool
    {
        // Check if monthly free slot needs reset first
        if ($this->shouldResetMonthlyFreeSlot()) {
            $this->resetMonthlyFreeSlot();
        }

        return $this->available_slots > 0;
    }

    /**
     * Reset monthly free slot
     * Called at the beginning of each month
     */
    public function resetMonthlyFreeSlot()
    {
        $this->has_monthly_free_slot = true;
        $this->last_free_slot_reset_at = now();
        $this->next_free_slot_reset_at = now()->addMonth()->startOfMonth();
        
        $this->recalculateTotalSlots();
        
        return $this;
    }

    /**
     * Check if monthly free slot should be reset
     * Reset if we've passed the next reset date
     */
    public function shouldResetMonthlyFreeSlot(): bool
    {
        // If never reset before, set it up
        if (!$this->next_free_slot_reset_at) {
            return true;
        }

        // If next reset date has passed
        return now()->greaterThanOrEqualTo($this->next_free_slot_reset_at);
    }

    /**
     * Add purchased slots
     */
    public function addPurchasedSlots(int $slots)
    {
        $this->purchased_slots += $slots;
        $this->recalculateTotalSlots();
        
        return $this;
    }

    /**
     * Remove purchased slots (e.g., for refunds)
     */
    public function removePurchasedSlots(int $slots)
    {
        $this->purchased_slots = max(0, $this->purchased_slots - $slots);
        $this->recalculateTotalSlots();
        
        return $this;
    }

    /**
     * Get slot usage percentage
     */
    public function getUsagePercentageAttribute()
    {
        if ($this->total_slots == 0) {
            return 100;
        }

        return round(($this->consumed_slots / $this->total_slots) * 100, 2);
    }

    /**
     * Get slot status for display
     */
    public function getStatusAttribute()
    {
        if ($this->available_slots == 0) {
            return 'no_slots';
        }

        if ($this->usage_percentage >= 80) {
            return 'low_slots';
        }

        return 'available';
    }

    /**
     * Get days until next free slot
     */
    public function getDaysUntilNextFreeSlotAttribute()
    {
        if (!$this->next_free_slot_reset_at) {
            return 0;
        }

        $days = now()->diffInDays($this->next_free_slot_reset_at, false);
        return max(0, ceil($days));
    }

    /**
     * Get breakdown of slot sources
     */
    public function getSlotBreakdownAttribute()
    {
        return [
            'monthly_free' => $this->has_monthly_free_slot ? 1 : 0,
            'purchased' => $this->purchased_slots,
            'total' => $this->total_slots,
            'consumed' => $this->consumed_slots,
            'available' => $this->available_slots,
            'usage_percentage' => $this->usage_percentage,
            'next_free_slot_in_days' => $this->days_until_next_free_slot,
        ];
    }

    /**
     * Scope to get users with low slots
     */
    public function scopeLowSlots($query)
    {
        return $query->where('available_slots', '<=', 2)
                     ->where('available_slots', '>', 0);
    }

    /**
     * Scope to get users with no slots
     */
    public function scopeNoSlots($query)
    {
        return $query->where('available_slots', '<=', 0);
    }

    /**
     * Scope to get users needing slot reset
     */
    public function scopeNeedingReset($query)
    {
        return $query->where(function($q) {
            $q->whereNull('next_free_slot_reset_at')
              ->orWhere('next_free_slot_reset_at', '<=', now());
        });
    }
}

