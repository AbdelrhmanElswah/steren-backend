<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'odoo_id',
        'title',
        'vin',
        'price',
        'price_currency',
        'year',
        'trim',
        'doors',
        'mileage',
        'origin_country',
        'gcc_import',
        'plate_status',
        'condition_type',
        'has_warranty',
        'is_inspected',
        'is_verified',
        'has_insurance',
        'user_id',
        'location_id',
        'studio_id',
        'car_brand_id',
        'car_body_type_id',
        'car_model_id',
        'car_condition_id',
        'mileage_type_id',
        'import_type_id',
        'fuel_type_id',
        'transmission_id',
        'exterior_color_id',
        'drive_type_id',
        'warranty_status_id',
        'cylinder_id',
        // New fields
        'engine_size',
        'engine_type',
        'horsepower',
        'torque',
        'acceleration_0_100',
        'top_speed',
        'seating_capacity',
        'mileage_efficiency',
        'length',
        'width',
        'height',
        'status',
        'status_id',
        'operator_decision',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'is_featured',
        'is_new_arrival',
        'description',
        'features',
        'specifications',
        'sold_at',
        'featured_at',
        // Promotion fields
        'listing_promotion_id',
        'promotion_price_paid',
        'promotion_currency',
        'promotion_expires_at',
        'is_promoted',
        'promotion_started_at',
        'paused_at',
        'archived_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'has_warranty' => 'boolean',
        'is_inspected' => 'boolean',
        'is_verified' => 'boolean',
        'has_insurance' => 'boolean',
        'is_sponsored' => 'boolean',
        'horsepower' => 'integer',
        'acceleration_0_100' => 'decimal:1',
        'top_speed' => 'integer',
        'seating_capacity' => 'integer',
        'mileage_efficiency' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_new_arrival' => 'boolean',
        'features' => 'array',
        'specifications' => 'array',
        'sold_at' => 'datetime',
        'featured_at' => 'datetime',
        'paused_at' => 'datetime',
        'archived_at' => 'datetime',
        // Promotion casts
        'promotion_price_paid' => 'decimal:2',
        'promotion_expires_at' => 'datetime',
        'is_promoted' => 'boolean',
        'promotion_started_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function listingPromotion() {
        return $this->belongsTo(ListingPromotion::class);
    }

    public function brand() {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function bodyType() {
        return $this->belongsTo(BodyType::class, 'car_body_type_id');
    }

    public function location() {
        return $this->belongsTo(Location::class);
    }

    public function studio() {
        return $this->belongsTo(DealerStudio::class, 'studio_id');
    }

    public function media() {
        return $this->morphMany(Media::class, 'modelable');
    }

    public function badges() {
        return $this->belongsToMany(ListingBadge::class, 'car_listing_badge');
    }

    public function carModel() {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function carCondition() {
        return $this->belongsTo(CarCondition::class);
    }

    public function mileageType() {
        return $this->belongsTo(MileageType::class);
    }

    public function importType() {
        return $this->belongsTo(ImportType::class);
    }

    public function fuelType() {
        return $this->belongsTo(FuelType::class);
    }

    public function transmission() {
        return $this->belongsTo(Transmission::class);
    }

    public function exteriorColor() {
        return $this->belongsTo(ExteriorColor::class);
    }

    public function driveType() {
        return $this->belongsTo(DriveType::class);
    }

    public function warrantyStatus() {
        return $this->belongsTo(WarrantyStatus::class);
    }

    public function cylinder() {
        return $this->belongsTo(CylinderOption::class);
    }

    public function carTrim() {
        return $this->belongsTo(CarTrim::class);
    }

    public function status() {
        return $this->belongsTo(Status::class);
    }

    public function features() {
        return $this->belongsToMany(CarFeature::class, 'car_listing_feature')
                    ->withPivot('is_included', 'notes')
                    ->withTimestamps();
    }

    public function specifications() {
        return $this->hasMany(CarSpecification::class);
    }

    public function damagedParts()
    {
        return $this->belongsToMany(CarPart::class, 'car_listing_damages')
            ->withPivot('condition')
            ->withTimestamps();
    }

    public function favorites() {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function isFavoritedBy($userId)
    {
        return $this->favorites()->where('user_id', $userId)->exists();
    }

    public function getFavoriteByUser($userId)
    {
        return $this->favorites()->where('user_id', $userId)->first();
    }

    public function vinDecoder() {
        return $this->hasOne(VinDecoder::class, 'vin', 'vin');
    }

    public function inspection() {
        return $this->hasOne(CarInspection::class);
    }

    /**
     * Get the analytics for this car listing
     */
    public function analytics()
    {
        return $this->hasMany(CarListingAnalytics::class);
    }

    /**
     * Get the documents for this car listing
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get inspection report document
     */
    public function inspectionReportDocument()
    {
        return $this->documents()->where('category', 'inspection_report')->first();
    }

    /**
     * Get warranty document
     */
    public function warrantyDocument()
    {
        return $this->documents()->where('category', 'warranty_document')->first();
    }

    /**
     * Get carfax report document
     */
    public function carfaxReportDocument()
    {
        return $this->documents()->where('category', 'carfax_report')->first();
    }

    /**
     * Get insurance document
     */
    public function insuranceDocument()
    {
        return $this->documents()->where('category', 'insurance_document')->first();
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
     * Get total views for this car listing
     */
    public function getTotalViewsAttribute()
    {
        return CarListingAnalytics::getTotalViews($this->id);
    }

    /**
     * Get total shares for this car listing
     */
    public function getTotalSharesAttribute()
    {
        return CarListingAnalytics::getTotalShares($this->id);
    }

    /**
     * Get total call taps for this car listing
     */
    public function getTotalCallTapsAttribute()
    {
        return CarListingAnalytics::getTotalCallTaps($this->id);
    }

    /**
     * Get total WhatsApp clicks for this car listing
     */
    public function getTotalWhatsAppClicksAttribute()
    {
        return CarListingAnalytics::getTotalWhatsAppClicks($this->id);
    }

    /**
     * Get total SMS clicks for this car listing
     */
    public function getTotalSmsClicksAttribute()
    {
        return CarListingAnalytics::getTotalSmsClicks($this->id);
    }

    /**
     * Get total email clicks for this car listing
     */
    public function getTotalEmailClicksAttribute()
    {
        return CarListingAnalytics::getTotalEmailClicks($this->id);
    }

    /**
     * Get total watchlist saves for this car listing
     */
    public function getTotalWatchlistSavesAttribute()
    {
        return CarListingAnalytics::getTotalWatchlistSaves($this->id);
    }

    /**
     * Get days active for this car listing
     */
    public function getDaysActiveAttribute()
    {
        return CarListingAnalytics::getDaysActive($this->id);
    }

    /**
     * Get full title for this car listing
     */
    public function getFullTitleAttribute()
    {
        $title = $this->title;
        
        if ($this->brand) {
            $title = $this->brand->name . ' ' . $title;
        }
        
        if ($this->year) {
            $title = $this->year . ' ' . $title;
        }
        
        return $title;
    }

    /**
     * Get formatted price for this car listing
     */
    public function getFormattedPriceAttribute()
    {
        return '$ ' . number_format($this->price, 0, '.', ',');
    }

    /**
     * Get active badges for this car listing
     */
    public function getActiveBadgesAttribute()
    {
        $badges = [];
        
        if ($this->has_warranty) {
            $badges[] = [
                'name' => 'Warranty',
                'icon' => 'shield',
                'color' => 'purple'
            ];
        }
        
        if ($this->is_inspected) {
            $badges[] = [
                'name' => 'Inspected',
                'icon' => 'magnifying-glass',
                'color' => 'green'
            ];
        }
        
        if ($this->is_verified) {
            $badges[] = [
                'name' => 'Verified',
                'icon' => 'checkmark',
                'color' => 'blue'
            ];
        }
        
        return $badges;
    }

    /**
     * Get status label for this car listing
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'active' => 'Active',
            'sold' => 'Sold',
            'paused' => 'Paused',
            'rejected' => 'Rejected',
            'pending' => 'Pending',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get promotion type for this car listing
     */
    public function getPromotionTypeAttribute()
    {
        if (!$this->is_promoted) {
            return null;
        }

        return match ($this->listing_promotion_id) {
            1 => 'Sponsored',
            2 => 'Featured',
            3 => 'Premium',
            default => 'Boosted',
        };
    }

    /**
     * Get active boosts for this car listing
     */
    public function activeBoosts()
    {
        return $this->hasMany(AdBoost::class)->where('expires_at', '>', now());
    }

    public function carfaxReport() {
        return $this->hasOne(CarfaxReport::class);
    }

    public function carDamages() {
        return $this->hasMany(CarDamage::class);
    }

    public function reports() {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Get all boosts for this car listing
     */
    public function boosts()
    {
        return $this->hasMany(AdBoost::class);
    }


    public function scopeByVin($query, $vin) {
        return $query->where('vin', strtoupper($vin));
    }

    public function scopeSimilar($query, $excludeId = null) {
        $query = $query->where('car_brand_id', $this->car_brand_id)
                      ->where('car_model_id', $this->car_model_id)
                      ->where('year', '>=', $this->year - 2)
                      ->where('year', '<=', $this->year + 2);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query;
    }


    // Scopes for filtering
    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query) {
        return $query->where('is_featured', true);
    }

    public function scopeNewArrivals($query) {
        return $query->where('is_new_arrival', true);
    }

    public function scopeSponsored($query) {
        return $query->where('is_sponsored', true);
    }

    public function scopeByStatus($query, $status) {
        return $query->where('status', $status);
    }

    public function scopeByPriceRange($query, $min, $max) {
        return $query->whereBetween('price', [$min, $max]);
    }

    public function scopeByYearRange($query, $min, $max) {
        return $query->whereBetween('year', [$min, $max]);
    }

    public function scopeByMileageRange($query, $min, $max) {
        return $query->whereBetween('mileage', [$min, $max]);
    }
}

