<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;

class DetailTransaction extends Model
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
        'transaction_uuid',
        'package_uuid',
        'type_of_purchase',
        'transaction_type',
        'detail_amount',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
