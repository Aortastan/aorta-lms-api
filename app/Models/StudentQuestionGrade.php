<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class StudentQuestionGrade extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'student_quiz_uuid',
        'student_quiz_type',
        'question_uuid',
        'user_uuid',
        'answer_text',
        'score_awarded',
        'feedback',
        'scored_by_uuid',
        'scored_at',
        'status',
    ];

    protected $casts = [
        'scored_at' => 'datetime',
        'score_awarded' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_uuid', 'uuid');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function scorer()
    {
        return $this->belongsTo(User::class, 'scored_by_uuid', 'uuid');
    }
}
