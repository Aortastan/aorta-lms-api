<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;

class CourseLesson extends Model
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
        'course_uuid',
        'title',
        'description',
        'is_have_quiz',
        'is_have_assignment',
    ];

    public function assignments()
    {
        return $this->hasMany(Assignment::class, "lesson_uuid");
    }

    public function quizzes()
    {
        return $this->hasMany(LessonQuiz::class, "lesson_uuid");
    }

    public function lectures()
    {
        return $this->hasMany(LessonLecture::class, "lesson_uuid");
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
