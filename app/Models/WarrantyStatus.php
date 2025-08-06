<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarrantyStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function listings()
    {
        return $this->hasMany(CarListing::class, 'car_condition_id');
    }
}
