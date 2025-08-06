<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Studio extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'location_id'];

    public function listings() {
        return $this->hasMany(CarListing::class);
    }

    public function location() {
        return $this->belongsTo(Location::class);
    }
}

