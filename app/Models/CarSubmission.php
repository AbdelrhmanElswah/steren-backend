<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'odoo_id',
        'user_id',
        'title',
        'vin',
        'description',
        'asking_price',
        'year',
        'trim',
        'doors',
        'mileage',
        'origin_country',
        'condition_type',
        'car_brand_id',
        'car_model_id',
        'car_body_type_id',
        'car_condition_id',
        'mileage_type_id',
        'import_type_id',
        'fuel_type_id',
        'transmission_id',
        'exterior_color_id',
        'drive_type_id',
        'warranty_status_id',
        'cylinder_id',
        'location_id',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'car_listing_id',
    ];

    protected $casts = [
        'asking_price' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function carListing()
    {
        return $this->belongsTo(CarListing::class);
    }

    public function vinDecoder()
    {
        return $this->hasOne(VinDecoder::class, 'vin', 'vin');
    }

    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function carModel()
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class, 'car_body_type_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function carCondition()
    {
        return $this->belongsTo(CarCondition::class);
    }

    public function mileageType()
    {
        return $this->belongsTo(MileageType::class);
    }

    public function importType()
    {
        return $this->belongsTo(ImportType::class);
    }

    public function fuelType()
    {
        return $this->belongsTo(FuelType::class);
    }

    public function transmission()
    {
        return $this->belongsTo(Transmission::class);
    }

    public function exteriorColor()
    {
        return $this->belongsTo(ExteriorColor::class);
    }

    public function driveType()
    {
        return $this->belongsTo(DriveType::class);
    }

    public function warrantyStatus()
    {
        return $this->belongsTo(WarrantyStatus::class);
    }

    public function cylinder()
    {
        return $this->belongsTo(CylinderOption::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'modelable');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByVin($query, $vin)
    {
        return $query->where('vin', strtoupper($vin));
    }

    public function approve($adminNotes = null, $reviewedBy = null)
    {
        $this->update([
            'status' => 'approved',
            'admin_notes' => $adminNotes,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function reject($adminNotes = null, $reviewedBy = null)
    {
        $this->update([
            'status' => 'rejected',
            'admin_notes' => $adminNotes,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function publish($carListingId)
    {
        $this->update([
            'status' => 'published',
            'car_listing_id' => $carListingId,
        ]);
    }

    /**
     * Get the documents for this car submission
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get technical specification document
     */
    public function technicalSpecificationDocument()
    {
        return $this->documents()->where('category', 'technical_specification')->first();
    }

    /**
     * Get maintenance record document
     */
    public function maintenanceRecordDocument()
    {
        return $this->documents()->where('category', 'maintenance_record')->first();
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
}
