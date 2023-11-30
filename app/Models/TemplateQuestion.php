<?php

namespace App\Models;
use Ramsey\Uuid\Uuid;


use Illuminate\Database\Eloquent\Model;

class TemplateQuestion extends Model
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
        'subject_uuid',
        'author_uuid',
        'title',
        'question_type',
        'question',
        'file_path',
        'url_path',
        'file_size',
        'file_duration',
        'type',
        'different_point',
        'point',
        'hint',
        'status',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_uuid', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_uuid', 'uuid');
    }

    public function answers()
    {
        return $this->hasMany(TemplateAnswer::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
