<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;


use Illuminate\Database\Eloquent\Model;

class TryoutSegmentTest extends Model
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
        'tryout_segment_uuid',
        'test_uuid',
        'attempt',
        'duration',
        'max_point',
        'passing_score',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_uuid', 'uuid');
    }

    public function studentTryouts()
    {
        return $this->hasMany(StudentTryout::class, 'package_test_uuid', 'uuid');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
