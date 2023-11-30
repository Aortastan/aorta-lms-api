<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
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
        'user_uuid',
        'external_id',
        'coupon_uuid',
        'transaction_amount',
        'payment_method_uuid',
        'transaction_status',
        'url',
    ];

    public function detailTransaction()
    {
        return $this->hasMany(DetailTransaction::class, 'transaction_uuid', 'uuid');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
