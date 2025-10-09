<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'color',
        'description'
    ];

    public function carListings()
    {
        return $this->hasMany(CarListing::class, 'status_id');
    }
}
