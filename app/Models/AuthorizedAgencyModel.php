<?php

namespace App\Models;

use App\Traits\ExcludesSoftDeleted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorizedAgencyModel extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = [
        'authorized_agency_id',
        'car_model_id',
        'gcc_import',
        'engine_size',
        'forced_induction',
        'fuel_type',
        'cylinders',
        'transmission',
        'seating_capacity',
        'drive_type',
        'price_start_from',
        'price_currency',
        'horse_power',
        'length_from',
        'length_to',
        'width_from',
        'width_to',
        'height_from',
        'height_to',
        'torque',
        'acceleration',
        'top_speed',
    ];

    protected $casts = [
        'price_start_from' => 'decimal:2',
        'horse_power' => 'integer',
        'seating_capacity' => 'integer',
        'length_from' => 'decimal:2',
        'length_to' => 'decimal:2',
        'width_from' => 'decimal:2',
        'width_to' => 'decimal:2',
        'height_from' => 'decimal:2',
        'height_to' => 'decimal:2',
        'acceleration' => 'decimal:2',
        'top_speed' => 'integer',
    ];

    protected $attributes = [
        'price_currency' => 'USD',
    ];

    protected static function booted(): void
    {
        static::deleting(function (AuthorizedAgencyModel $model) {
            // Remove outer and inner media so files are cleaned up by MediaObserver
            if ($model->relationLoaded('outerImage')) {
                $model->outerImage?->delete();
            } else {
                $model->outerImage()->delete();
            }

            if ($model->relationLoaded('innerImages')) {
                $model->innerImages->each->delete();
            } else {
                $model->innerImages()->each(function ($media) {
                    $media->delete();
                });
            }
        });
    }

    public function authorizedAgency()
    {
        return $this->belongsTo(AuthorizedAgency::class);
    }

    public function carModel()
    {
        return $this->belongsTo(CarModel::class);
    }

    public function outerImage()
    {
        return $this->morphOne(Media::class, 'modelable')
            ->where('info', 'agency_model_outer');
    }

    public function innerImages()
    {
        return $this->morphMany(Media::class, 'modelable')
            ->where('info', 'agency_model_inner')
            ->orderBy('order');
    }
}


