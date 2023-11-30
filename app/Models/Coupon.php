<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public $incrementing = false; // Non-incrementing primary key
    protected $keyType = 'string'; // Primary key type is string
    protected $primaryKey = 'uuid'; // Name of the UUID column

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type_coupon',
        'type_limit',
        'code',
        'price',
        'discount',
        'limit',
        'expired_date',
    ];

    public function claimed()
    {
        return $this->hasMany(ClaimedCoupon::class, "coupon_uuid");
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
