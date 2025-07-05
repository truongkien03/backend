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

    public function toArray()
    {
        $array = parent::toArray();
        $array['hasCredential'] = $this->password ? true : false;
        return $array;
    }
}
