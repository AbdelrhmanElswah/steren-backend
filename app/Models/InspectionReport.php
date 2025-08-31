<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionReport extends Model
{
    protected $fillable = ['car_listing_id'];

    public function listing()
    {
        return $this->belongsTo(CarListing::class, 'car_listing_id');
    }
}
