<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = [
        'partner_id', 'odoo_id', 'full_name', 'email', 'phone', 'password', 'language_id', 'otp_code', 'otp_expires_at',
        'profile_picture',
        'birthday',
        'is_verified',
        'is_dealer',
        'is_suspended',
        'suspended_at',
        'suspension_reason',
        'email_verified_at',
        'phone_verified_at',
        'social_provider',
        'social_id',
        'firebase_uid',
        'firebase_claims',
        'notifications_enabled',
        'marketing_emails_enabled',
        'notification_preferences',
        'last_login_at',
        'last_activity_at',
        'is_pending',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_dealer' => 'boolean',
        'is_suspended' => 'boolean',
        'is_pending' => 'boolean',
        'suspended_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'birthday' => 'date',
        'firebase_claims' => 'array',
        'notifications_enabled' => 'boolean',
        'marketing_emails_enabled' => 'boolean',
        'notification_preferences' => 'array',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'main');
    }

    public function carListings()
    {
        return $this->hasMany(CarListing::class);
    }

    public function carSubmissions()
    {
        return $this->hasMany(CarSubmission::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(CarListing::class, 'favorites')->withTimestamps();
    }

    public function searchHistory()
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function savedSearches()
    {
        return $this->hasMany(SearchHistory::class)->saved();
    }

    public function searchHistoryWithNotifications()
    {
        return $this->hasMany(SearchHistory::class)->withNotifications();
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function reviewedReports()
    {
        return $this->hasMany(Report::class, 'reviewed_by');
    }

    public function verifiedCarDamages()
    {
        return $this->hasMany(CarDamage::class, 'verified_by');
    }

    public function unreadNotifications()
    {
        return $this->notifications()->unread();
    }

    public function reviewedSubmissions()
    {
        return $this->hasMany(CarSubmission::class, 'reviewed_by');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest();
    }

    public function analytics()
    {
        return $this->hasMany(UserAnalytics::class);
    }

    /**
     * Get ad boosts for this user
     */
    public function adBoosts()
    {
        return $this->hasMany(AdBoost::class);
    }

    /**
     * Get payment processing records for this user
     */
    public function paymentProcessing()
    {
        return $this->hasMany(PaymentProcessing::class);
    }

    /**
     * Get user's slot record (for free users)
     */
    public function userSlot()
    {
        return $this->hasOne(UserSlot::class);
    }

    /**
     * Get user's slot purchases
     */
    public function slotPurchases()
    {
        return $this->hasMany(SlotPurchase::class);
    }

    /**
     * Get completed slot purchases
     */
    public function completedSlotPurchases()
    {
        return $this->hasMany(SlotPurchase::class)->completed();
    }

    /**
     * Get dealer applications for this user
     */
    public function dealerApplications()
    {
        return $this->hasMany(DealerApplication::class, 'partner_id');
    }

    /**
     * Get the latest dealer application
     */
    public function latestDealerApplication()
    {
        return $this->hasOne(DealerApplication::class, 'partner_id')->latest();
    }

    /**
     * Get active dealer application
     */
    public function activeDealerApplication()
    {
        return $this->hasOne(DealerApplication::class, 'partner_id')->active();
    }

    /**
     * Get the user's device permissions
     */
    public function devicePermissions()
    {
        return $this->hasOne(UserDevicePermission::class);
    }

    /**
     * Get user's display name
     */
    public function getDisplayNameAttribute()
    {
        return $this->attributes['display_name'] ?? $this->full_name ?? $this->name ?? null;
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedPhoneAttribute()
    {
        if (!$this->phone) {
            return null;
        }

        // Format phone number for display
        return '+964 ' . substr($this->phone, 0, 3) . ' ' . substr($this->phone, 3, 3) . ' ' . substr($this->phone, 6);
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription()
    {
        return $this->activeSubscription && $this->activeSubscription->isActive();
    }

    /**
     * Get user's current plan
     */
    public function getCurrentPlanAttribute()
    {
        return $this->activeSubscription?->subscriptionPlan;
    }

    /**
     * Check if user can create more listings
     */
    public function canCreateListing()
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        $plan = $this->current_plan;
        if (!$plan || $plan->max_listings === null) {
            return true; // Unlimited
        }

        $currentListingsCount = $this->carListings()
            ->whereIn('status', ['active', 'pending'])
            ->count();

        return $currentListingsCount < $plan->max_listings;
    }

    /**
     * Get remaining listing slots
     */
    public function getRemainingListingsAttribute()
    {
        if (!$this->hasActiveSubscription()) {
            return 0;
        }

        $plan = $this->current_plan;
        if (!$plan || $plan->max_listings === null) {
            return null; // Unlimited
        }

        $currentListingsCount = $this->carListings()
            ->whereIn('status', ['active', 'pending'])
            ->count();

        return max(0, $plan->max_listings - $currentListingsCount);
    }

    /**
     * Get maximum photos allowed per listing
     */
    public function getMaxPhotosAttribute()
    {
        if (!$this->hasActiveSubscription()) {
            return 0;
        }

        $plan = $this->current_plan;
        return $plan ? $plan->max_photos : 0;
    }

    /**
     * Check if user can upload specified number of photos
     */
    public function canUploadPhotos(int $photoCount): bool
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        $plan = $this->current_plan;
        if (!$plan) {
            return false;
        }

        // Null means unlimited
        if ($plan->max_photos === null) {
            return true;
        }

        return $photoCount <= $plan->max_photos;
    }

    /**
     * Check if subscription is expiring soon (within 7 days)
     */
    public function isSubscriptionExpiringSoon()
    {
        $subscription = $this->activeSubscription;
        
        if (!$subscription || !$subscription->expires_at) {
            return false;
        }

        return $subscription->expires_at->diffInDays(now()) <= 7 && $subscription->expires_at->isFuture();
    }

    /**
     * Get subscription status
     */
    public function getSubscriptionStatusAttribute()
    {
        if (!$this->hasActiveSubscription()) {
            return 'no_subscription';
        }

        $subscription = $this->activeSubscription;

        if ($subscription->isExpired()) {
            return 'expired';
        }

        if ($this->isSubscriptionExpiringSoon()) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Update user's last activity
     */
    public function updateLastActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Update user's last login
     */
    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get formatted birthday
     */
    public function getFormattedBirthdayAttribute()
    {
        if (!$this->birthday) {
            return null;
        }

        return $this->birthday->format('d/m/Y');
    }

    /**
     * Get age
     */
    public function getAgeAttribute()
    {
        if (!$this->birthday) {
            return null;
        }

        return $this->birthday->age;
    }

    /**
     * Check if user is verified
     */
    public function isVerified()
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * Mark user as verified
     */
    public function markAsVerified()
    {
        $this->update(['phone_verified_at' => now()]);
    }

    /**
     * Check if user is a dealer
     * TEMPORARY: Skip payment requirement until payment system is implemented
     */
    public function isDealer()
    {
        return $this->activeDealerApplication && $this->activeDealerApplication->isApproved();
    }

    /**
     * Check if user has pending dealer application
     */
    public function hasPendingDealerApplication()
    {
        return $this->dealerApplications()->pending()->exists();
    }

    /**
     * Check if user has approved dealer application
     */
    public function hasApprovedDealerApplication()
    {
        return $this->dealerApplications()->approved()->exists();
    }

    /**
     * Check if user's dealer status is expiring soon
     */
    public function isDealerExpiringSoon()
    {
        $dealerApp = $this->activeDealerApplication;
        return $dealerApp && $dealerApp->isExpiringSoon();
    }

    /**
     * Check if user's dealer status is expired
     */
    public function isDealerExpired()
    {
        $dealerApp = $this->latestDealerApplication;
        return $dealerApp && $dealerApp->isExpired();
    }

    /**
     * Get dealer status for display
     */
    public function getDealerStatusAttribute()
    {
        $latestApp = $this->latestDealerApplication;
        
        if (!$latestApp) {
            return null;
        }

        if ($latestApp->isActive()) {
            return 'verified';
        }

        if ($latestApp->isExpiringSoon()) {
            return 'expiring_soon';
        }

        if ($latestApp->isExpired()) {
            return 'expired';
        }

        return $latestApp->status;
    }

    /**
     * Check if account is suspended
     */
    public function isSuspended(): bool
    {
        return (bool) $this->is_suspended;
    }

    /**
     * Suspend the account
     */
    public function suspend(string $reason = null)
    {
        $this->update([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => $reason ?? 'Subscription expired without renewal after grace period',
        ]);

        return $this;
    }

    /**
     * Unsuspend the account
     */
    public function unsuspend()
    {
        $this->update([
            'is_suspended' => false,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return $this;
    }

    /**
     * Get account status for display
     */
    public function getAccountStatusAttribute()
    {
        if ($this->is_suspended) {
            return 'suspended';
        }

        $subscription = $this->activeSubscription;
        
        if (!$subscription) {
            return 'free';
        }

        if ($subscription->isInGracePeriod()) {
            return 'grace_period';
        }

        if ($subscription->isActive()) {
            return 'active';
        }

        return 'free';
    }
}
