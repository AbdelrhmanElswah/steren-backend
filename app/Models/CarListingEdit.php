<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;

class CarListingEdit extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = [
        'car_listing_id',
        'odoo_id',
        'odoo_car_listing_id',
        'previous_state',
        'edit_type',
        'status',
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
        'city_id',
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
        'engine_size',
        'engine_unit',
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
        'forced_induction',
        'operator_decision',
        'status_id',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'is_featured',
        'is_new_arrival',
        'description',
        'damaged_areas',
        'features',
        'sold_at',
        'featured_at',
        'paused_at',
        'archived_at',
        'listing_promotion_id',
        'promotion_price_paid',
        'promotion_currency',
        'promotion_expires_at',
        'is_promoted',
        'promotion_started_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'has_warranty' => 'boolean',
        'is_inspected' => 'boolean',
        'is_verified' => 'boolean',
        'has_insurance' => 'boolean',
        'horsepower' => 'integer',
        'acceleration_0_100' => 'decimal:1',
        'top_speed' => 'integer',
        'seating_capacity' => 'integer',
        'damaged_areas' => 'array',
        'features' => 'array',
        'mileage_efficiency' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_new_arrival' => 'boolean',
        'sold_at' => 'datetime',
        'featured_at' => 'datetime',
        'paused_at' => 'datetime',
        'archived_at' => 'datetime',
        'promotion_price_paid' => 'decimal:2',
        'promotion_expires_at' => 'datetime',
        'is_promoted' => 'boolean',
        'promotion_started_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the car listing this edit belongs to
     */
    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    /**
     * Get the user who created this edit
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get brand relationship
     */
    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    /**
     * Get model relationship
     */
    public function carModel()
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    /**
     * Get body type relationship
     */
    public function bodyType()
    {
        return $this->belongsTo(BodyType::class, 'car_body_type_id');
    }

    /**
     * Get city relationship
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get media relationship
     */
    public function media()
    {
        return $this->morphMany(Media::class, 'modelable');
    }

    /**
     * Compute edit_type from previous_state
     */
    public function computeEditType()
    {
        if ($this->previous_state === 'rejected') {
            return 'resubmission';
        }
        return 'edit';
    }

    /**
     * Boot method to auto-compute edit_type
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($edit) {
            if (empty($edit->edit_type) && !empty($edit->previous_state)) {
                $edit->edit_type = $edit->previous_state === 'rejected' ? 'resubmission' : 'edit';
            }
        });

        static::updating(function ($edit) {
            if ($edit->isDirty('previous_state') && empty($edit->edit_type)) {
                $edit->edit_type = $edit->previous_state === 'rejected' ? 'resubmission' : 'edit';
            }
        });
    }
}
