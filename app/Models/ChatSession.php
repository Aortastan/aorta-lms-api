<?php

namespace App\Models;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    public $incrementing = false; // Non-incrementing primary key
    protected $keyType = 'string'; // Primary key type is string
    protected $primaryKey = 'uuid'; // Name of the UUID column

    protected $fillable = [
        'user_uuid',
        'visitor_key_hash',
        'share_code',
        'name',
        'phone',
        'bot_state',
        'last_message_preview',
        'last_message_at',
        'last_user_message_at',
        'admin_read_at',
        'handoff_at',
    ];

    protected $hidden = [
        'id',
        'visitor_key_hash',
        'share_code',
        'bot_state',
    ];

    protected $casts = [
        'bot_state' => 'array',
        'last_message_at' => 'datetime',
        'last_user_message_at' => 'datetime',
        'admin_read_at' => 'datetime',
        'handoff_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
            $model->share_code = self::newShareCode();
        });
    }

    /** 12 karakter acak [A-Za-z0-9] (~71 bit) — tidak bisa ditebak. */
    public static function newShareCode(): string
    {
        do {
            $code = Str::random(12);
        } while (self::where('share_code', $code)->exists());

        return $code;
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_uuid', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public static function hashVisitorKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
