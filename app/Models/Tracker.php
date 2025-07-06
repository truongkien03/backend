<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tracker extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'description',
        'note'
    ];

    protected $casts = [
        'description' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
