<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarPart extends Model
{
    protected $fillable = ['name', 'display_name'];

    public function carListings()
    {
        return $this->belongsToMany(CarListing::class, 'car_listing_damages')
            ->withPivot('condition')
            ->withTimestamps();
    }
}
