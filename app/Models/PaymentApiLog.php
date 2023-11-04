<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentApiLog extends Model
{
    protected $fillable = [
        'endpoint_url',
        'method',
        'status',
    ];
}
