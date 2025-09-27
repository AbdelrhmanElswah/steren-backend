<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Studio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'odoo_id',
        'name', 
        'location_id', 
        'logo_url', 
        'description', 
        'contact_info', 
        'is_featured',
        'tagline'
    ];

    protected $casts = [
        'contact_info' => 'array',
        'is_featured' => 'boolean',
    ];

    public function listings() {
        return $this->hasMany(CarListing::class);
    }

    public function location() {
        return $this->belongsTo(Location::class);
    }

    public function featuredListings() {
        return $this->hasMany(CarListing::class)->where('is_sponsored', true);
    }
}

