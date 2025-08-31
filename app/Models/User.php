<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'phone', 'password', 'language_id', 'otp_code', 'otp_expires_at',
        'profile_picture',
        'display_name',
        'birthday',
        'language',
        'theme',
        'is_verified',
        'email_verified_at',
        'phone_verified_at',
        'social_provider',
        'social_id',
        'notifications_enabled',
        'marketing_emails_enabled',
        'notification_preferences',
        'device_permissions',
        'total_listings',
        'total_views',
        'total_favorites',
        'last_login_at',
        'last_activity_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_verified' => 'boolean',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'birthday' => 'date',
        'notifications_enabled' => 'boolean',
        'marketing_emails_enabled' => 'boolean',
        'notification_preferences' => 'array',
        'device_permissions' => 'array',
        'total_listings' => 'integer',
        'total_views' => 'integer',
        'total_favorites' => 'integer',
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
        return $this->hasOne(UserSubscription::class)->active()->latest();
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
     * Get user's display name
     */
    public function getDisplayNameAttribute()
    {
        return $this->display_name ?: $this->full_name ?: $this->name;
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
        return $this->is_verified;
    }

    /**
     * Mark user as verified
     */
    public function markAsVerified()
    {
        $this->update(['is_verified' => true]);
    }
}
