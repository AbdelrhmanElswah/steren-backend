<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\DealerNotificationService;

class DealerApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'seller_type',
        'owner_manager_name',
        'phone_number',
        'email',
        'dealership_company_name',
        'vehicles_in_stock',
        'years_in_business',
        'city_address',
        'automotive_brand_represented',
        'authorization_letter_path',
        'business_license_number',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
        'subscription_plan_id',
        'payment_completed',
        'approved_at',
        'expires_at',
        'terms_accepted',
        'information_confirmed',
    ];

    protected $casts = [
        'vehicles_in_stock' => 'integer',
        'years_in_business' => 'integer',
        'payment_completed' => 'boolean',
        'terms_accepted' => 'boolean',
        'information_confirmed' => 'boolean',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns this application
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Get the user who reviewed this application
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to get pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get applications under review
     */
    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    /**
     * Scope to get approved applications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to get active dealer applications (approved and not expired)
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'approved')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Check if application is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if application is under review
     */
    public function isUnderReview()
    {
        return $this->status === 'under_review';
    }

    /**
     * Check if application is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if application is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if dealer status is active (approved and not expired)
     */
    public function isActive()
    {
        return $this->isApproved() && 
               $this->payment_completed &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Check if dealer status is about to expire (within 3 days)
     */
    public function isExpiringSoon()
    {
        return $this->isApproved() && 
               $this->expires_at && 
               $this->expires_at->isFuture() && 
               $this->expires_at->diffInDays(now()) <= 3;
    }

    /**
     * Check if dealer status is expired
     */
    public function isExpired()
    {
        return $this->isApproved() && 
               $this->expires_at && 
               $this->expires_at->isPast();
    }

    /**
     * Approve the application
     */
    public function approve($reviewedBy = null)
    {
        $this->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
            'approved_at' => now(),
        ]);

        // Send notification
        app(DealerNotificationService::class)->notifyApplicationStatusChange($this);
    }

    /**
     * Reject the application
     */
    public function reject($reason = null, $reviewedBy = null)
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);

        // Send notification
        app(DealerNotificationService::class)->notifyApplicationStatusChange($this);
    }

    /**
     * Set application under review
     */
    public function setUnderReview($reviewedBy = null)
    {
        $this->update([
            'status' => 'under_review',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);

        // Send notification
        app(DealerNotificationService::class)->notifyApplicationStatusChange($this);
    }

    /**
     * Complete payment and activate dealer status
     */
    public function completePayment()
    {
        $this->update([
            'payment_completed' => true,
            'approved_at' => now(),
        ]);

        // Set expiration date based on subscription plan
        if ($this->subscriptionPlan) {
            $billingCycle = $this->subscriptionPlan->billing_cycle;
            $expiresAt = $billingCycle === 'yearly' 
                ? now()->addYear() 
                : now()->addMonth();
            
            $this->update(['expires_at' => $expiresAt]);
        }

        // Send activation notification
        app(DealerNotificationService::class)->notifyDealerActivated($this);
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute()
    {
        if (!$this->expires_at) {
            return null;
        }

        return $this->expires_at->diffInDays(now());
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute()
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'under_review' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
