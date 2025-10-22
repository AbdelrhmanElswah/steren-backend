<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludesSoftDeleted;

class City extends Model
{
    use HasFactory, SoftDeletes, ExcludesSoftDeleted;

    protected $fillable = ['odoo_id', 'name'];

    public function userPreferences()
    {
        return $this->hasMany(UserPreference::class);
    }
}

