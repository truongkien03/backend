<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'gplx_front_url',
        'gplx_back_url',
        'baohiem_url',
        'dangky_xe_url',
        'cmnd_front_url',
        'cmnd_back_url',
        'reference_code',
    ];
}
