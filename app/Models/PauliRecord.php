<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class PauliRecord extends Model
{
    protected $table = 'pauli_records';

    protected $fillable = [
        'selected_time',
        'questions_attempted',
        'total_correct',
        'total_wrong',
        'time_start',
        'time_end',
        'date',
        'user_uuid',
    ];

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_pauli_record', 'pauli_record_id', 'package_uuid');
    }

    public function details()
    {
        return $this->hasMany(PauliRecordDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

}
