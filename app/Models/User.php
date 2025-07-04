<?php

namespace App\Models;

use App\Traits\FcmNotifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use FcmNotifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'address',
        'password',
        'fcm_token',
        'avatar'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'address' => 'json',
        'fcm_token' => 'json'
    ];

    public function getHasCredentialAttribute()
    {
        if ($this->email && $this->password) {
            return true;
        }

        return false;
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['hasCredential'] = $this->password ? true : false;
        return $array;
    }
}
