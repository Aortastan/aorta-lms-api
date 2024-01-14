<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;


use Illuminate\Database\Eloquent\Model;

class TryoutSegment extends Model
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
        'tryout_uuid',
        'title',
    ];

    public function tryoutSegmentTests()
    {
        return $this->hasMany(TryoutSegmentTest::class, 'tryout_segment_uuid', 'uuid');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
