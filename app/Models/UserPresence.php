<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPresence extends Model
{
    public $timestamps = false;

    const ONLINE_SECONDS = 150;
    const ACTIVITIES = ['browsing', 'tryout', 'quiz', 'posttest', 'pauli'];

    protected $fillable = [
        'user_uuid',
        'activity',
        'test_ref_uuid',
        'package_uuid',
        'path',
        'activity_started_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'activity_started_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public static function onlineSince()
    {
        return now()->subSeconds(self::ONLINE_SECONDS);
    }

    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>=', self::onlineSince());
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
