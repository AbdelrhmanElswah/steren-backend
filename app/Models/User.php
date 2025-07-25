<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'phone', 'password', 'language_id' , 'otp_code', 'otp_expires_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function media()
    {
        return $this->morphOne(Media::class, 'modelable')->where('info', 'main');
    }
}
