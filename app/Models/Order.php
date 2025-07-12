<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver_id',
        'from_address',
        'to_address',
        'items',
        'shipping_cost',
        'distance',
        'discount',
        'status_code',
        'completed_at',
        'driver_rate',
        'user_note',
        'driver_note',
        'driver_accept_at',
        'receiver',
        'is_sharable',
        'except_drivers'
    ];

    protected $casts = [
        'from_address' => 'array',
        'to_address' => 'array',
        'items' => 'array',
        'receiver' => 'json',
        'except_drivers' => 'array'
    ];

    protected $appends = [
        'customerAvatar',
        'customerName'
    ];

    protected $with = ['customer'];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function status()
    {
        return $this->hasOne(Tracker::class)->latest();
    }

    public function tracker()
    {
        return $this->hasMany(Tracker::class);
    }

    public function proofImages()
    {
        return $this->hasMany(OrderProofImage::class);
    }

    public function getCustomerAvatarAttribute()
    {
        return $this->customer->avatar ?? '';
    }

    public function getCustomerNameAttribute()
    {
        return $this->customer->name ?? '';
    }
}
