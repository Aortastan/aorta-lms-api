<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PauliRecord;

class PauliRecordDetail extends Model
{
    use HasFactory;

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
