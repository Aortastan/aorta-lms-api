<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\PauliRecordDetail;

class PauliRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'selected_time',
        'questions_attempted',
        'total_correct',
        'total_wrong',
        'time_start',
        'time_end',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(PauliRecordDetail::class);
    }
}
