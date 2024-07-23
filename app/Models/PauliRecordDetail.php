<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class PauliRecordDetail extends Model
{
    protected $table = 'pauli_record_details';

    protected $fillable = [
        'pauli_record_id',
        'correct',
        'wrong',
        'time',
    ];

    public function pauliRecord()
    {
        return $this->belongsTo(PauliRecord::class);
    }
}
