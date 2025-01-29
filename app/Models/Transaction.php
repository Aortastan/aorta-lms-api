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
        'transaction_amount',
        'payment_method_uuid',
        'transaction_status',
        'url',
        'expiry_date',
        'updated_at',
        'created_at'
    ];

    public function detailTransaction()
    {
        return $this->hasMany(DetailTransaction::class, 'transaction_uuid', 'uuid');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function payment()
    {
        return $this->belongsTo(PaymentGatewaySetting::class, 'payment_method_uuid', 'uuid');
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
