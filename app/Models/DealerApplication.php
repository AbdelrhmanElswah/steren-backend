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
        'odoo_id',
        'seller_type',
        'owner_manager_name',
        'phone_number',
        'email',
        'dealership_company_name',
        'vehicles_in_stock',
        'years_in_business',
        'city_address',
        'automotive_brand_represented',
        'business_license_number',
        'status_id',
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
     * Get the documents for this dealer application
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get business license document
     */
    public function businessLicenseDocument()
    {
        return $this->documents()->where('category', 'business_license')->first();
    }

    /**
     * Get authorization letter document
     */
    public function authorizationLetterDocument()
    {
        return $this->documents()->where('category', 'authorization_letter')->first();
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

    /**
     * Check if all required documents are verified
     */
    public function hasAllDocumentsVerified()
    {
        $requiredCategories = ['business_license', 'authorization_letter'];
        
        foreach ($requiredCategories as $category) {
            $document = $this->documents()->where('category', $category)->first();
            if (!$document || !$document->is_verified) {
                return false;
            }
        }
        
        return true;
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
     * Get the status of this application
     */
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get pending applications
     */
    public function scopePending($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->where('name', 'pending')->where('type', 'dealer_application');
        });
    }

    /**
     * Scope to get applications under review
     */
    public function scopeUnderReview($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->where('name', 'under_review')->where('type', 'dealer_application');
        });
    }

    /**
     * Scope to get approved applications
     */
    public function scopeApproved($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->where('name', 'approved')->where('type', 'dealer_application');
        });
    }

    /**
     * Scope to get rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->where('name', 'rejected')->where('type', 'dealer_application');
        });
    }

    /**
     * Scope to get active dealer applications (approved and not expired)
     */
    public function scopeActive($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->where('name', 'approved')->where('type', 'dealer_application');
        })->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if application is pending
     */
    public function isPending()
    {
        return $this->status && $this->status->name === 'pending' && $this->status->type === 'dealer_application';
    }

    /**
     * Check if application is under review
     */
    public function isUnderReview()
    {
        return $this->status && $this->status->name === 'under_review' && $this->status->type === 'dealer_application';
    }

    /**
     * Check if application is approved
     */
    public function isApproved()
    {
        return $this->status && $this->status->name === 'approved' && $this->status->type === 'dealer_application';
    }

    /**
     * Check if application is rejected
     */
    public function isRejected()
    {
        return $this->status && $this->status->name === 'rejected' && $this->status->type === 'dealer_application';
    }

    /**
     * Check if application is verified (payment completed)
     */
    public function isVerified()
    {
        return $this->status && $this->status->name === 'verified' && $this->status->type === 'dealer_application';
    }

    /**
     * Check if dealer status is active (verified and not expired)
     * Only dealers with "verified" status can post listings
     */
    public function isActive()
    {
        return $this->isVerified() && 
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
        $approvedStatus = Status::findByNameAndType('approved', 'dealer_application');
        
        $this->update([
            'status_id' => $approvedStatus?->id,
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
        $rejectedStatus = Status::findByNameAndType('rejected', 'dealer_application');
        
        $this->update([
            'status_id' => $rejectedStatus?->id,
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
        $underReviewStatus = Status::findByNameAndType('under_review', 'dealer_application');
        
        $this->update([
            'status_id' => $underReviewStatus?->id,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);

        // Send notification
        app(DealerNotificationService::class)->notifyApplicationStatusChange($this);
    }

    /**
     * Complete payment and activate dealer status
     * Sets status to "verified" after payment
     */
    public function completePayment()
    {
        $verifiedStatus = Status::findByNameAndType('verified', 'dealer_application');
        
        $this->update([
            'payment_completed' => true,
            'approved_at' => now(),
            'status_id' => $verifiedStatus?->id,
        ]);

        if ($this->subscriptionPlan) {
            $billingCycle = $this->subscriptionPlan->billing_cycle;
            $expiresAt = $billingCycle === 'yearly' 
                ? now()->addYear() 
                : now()->addMonth();
            
            $this->update(['expires_at' => $expiresAt]);
        }

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
        return $this->status?->color ?? '#6B7280';
    }
}
