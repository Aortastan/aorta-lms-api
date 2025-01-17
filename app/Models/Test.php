<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;

class Test extends Model
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
        'test_type',
        'title',
        'status',
        'test_category',
        'student_title_display'
    ];

    public function questions()
    {
        return $this->hasMany(QuestionTest::class);
    }

    public function tags()
    {
        return $this->hasMany(TestTag::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
