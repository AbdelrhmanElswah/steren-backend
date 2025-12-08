<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;

class CarModel extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = ['odoo_id', 'name', 'name_ar', 'name_kr', 'car_brand_id'];

    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function listings()
    {
        return $this->hasMany(CarListing::class, 'car_model_id');
    }

    public function trims()
    {
        return $this->hasMany(CarTrim::class);
    }
}

