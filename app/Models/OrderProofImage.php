<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProofImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'shipper_id',
        'image_url',
        'note',
    ];
}
