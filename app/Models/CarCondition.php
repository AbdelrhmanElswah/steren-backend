<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarCondition extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function listings()
    {
        return $this->hasMany(CarListing::class, 'car_condition_id');
    }
}
