<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Package extends Model
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
        'category_uuid',
        'package_type',
        'name',
        'price_lifetime',
        'price_one_month',
        'price_three_months',
        'price_six_months',
        'price_one_year',
        'learner_accesibility',
        'image',
        'discount',
        'is_membership',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_uuid', 'uuid');
    }

    public function packageTests()
    {
        return $this->hasMany(PackageTest::class);
    }

    public function packageCourses()
    {
        return $this->hasMany(PackageCourse::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
