<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Blog extends Model
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
        'user_uuid',
        'category_uuid',
        'title',
        'slug',
        'body',
        'image',
        'status',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_uuid');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
