<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;


class LessonAttendances extends Model
{


    public $timestamps = true;
    public $incrementing = false; // Non-incrementing primary key
    protected $keyType = 'string'; // Primary key type is string
    protected $primaryKey = 'uuid'; // Name of the UUID column

    protected $fillable = [
        'lesson_lecture_uuid',
        'user_uuid',
        'start_attendance',
        'end_attendance',
        'note',
        'note_status',
        'note_approved_by',
    ];

    public function lesson()
    {
        return $this->hasMany(CourseLesson::class, 'lesson_lecture_uuid', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_uuid',
            'uuid'
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
