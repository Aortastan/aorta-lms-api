<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;

use Illuminate\Database\Eloquent\Model;

class ExamAgenda extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    const MAX_ACTIVE = 4;
    const MAX_PACKAGES = 6;

    protected $fillable = [
        'stage_label',
        'title',
        'description',
        'start_at',
        'end_at',
        'visibility',
        'package_uuids',
        'is_active',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'package_uuids' => 'array',
        'is_active' => 'boolean',
        'start_at' => 'datetime:Y-m-d\TH:i:sP',
        'end_at' => 'datetime:Y-m-d\TH:i:sP',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }

    public function scopeNotFinished($query)
    {
        return $query->where(function ($q) {
            $q->where('end_at', '>', now())
                ->orWhere(function ($q) {
                    $q->whereNull('end_at')->where('start_at', '>=', now()->startOfDay());
                });
        });
    }

    public function finishesAt()
    {
        return $this->end_at ?: $this->start_at->copy()->endOfDay();
    }

    public function toPublicArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'stage_label' => $this->stage_label,
            'title' => $this->title,
            'description' => $this->description,
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at ? $this->end_at->toIso8601String() : null,
            'finishes_at' => $this->finishesAt()->toIso8601String(),
        ];
    }
}
