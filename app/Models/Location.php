<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;

class Location extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = ['odoo_id', 'name'];

}

