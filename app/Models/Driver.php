<?php

namespace App\Models;

use App\Traits\FcmNotifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Driver extends Authenticatable
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
        'current_location',
        'status',
        'review_rate',
        'password',
        'delivering_order_id',
        'avatar',
        'fcm_token'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'current_location' => 'json'
    ];

    public function profile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function sharingGroup()
    {
        return $this->hasManyThrough(Driver::class, Group::class, 'master_id', 'id', 'id', 'member_id');
    }

    public function profileFilled()
    {
        $requiredFields = [
            'gplx_front_url',
            'gplx_back_url',
            'baohiem_url',
            'cmnd_front_url',
            'cmnd_back_url',
        ];

        if (empty($this->profile)) {
            return false;
        }

        foreach ($requiredFields as $requiredField) {
            if (empty($this->profile->$requiredField)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if driver has password set
     */
    public function getHasPasswordAttribute()
    {
        return !is_null($this->password);
    }

    /**
     * Get the avatar URL attribute.
     * Tự động chuyển đổi đường dẫn avatar thành URL đầy đủ
     */
    public function getAvatarAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        // Nếu đã là URL đầy đủ thì trả về như cũ
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        // Nếu là đường dẫn tương đối bắt đầu bằng storage/, chuyển thành URL đầy đủ
        if (strpos($value, 'storage/') === 0) {
            return url($value);
        }
        
        // Nếu bắt đầu bằng /storage/, bỏ dấu / đầu
        if (strpos($value, '/storage/') === 0) {
            return url(ltrim($value, '/'));
        }
        
        // Nếu chỉ là tên file (có thể từ database cũ), thêm prefix storage/avatars/
        if (!str_contains($value, '/')) {
            return url('storage/avatars/' . $value);
        }
        
        // Các trường hợp khác, coi như đường dẫn tương đối
        return url('storage/' . ltrim($value, '/'));
    }

    /**
     * Override toArray to include hasPassword attribute
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['hasPassword'] = $this->has_password;
        return $array;
    }
}
