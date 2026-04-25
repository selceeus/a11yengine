<?php

namespace App\Models;

use App\Enums\ActivityLogEvent;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'agency_id',
        'user_id',
        'actor_type',
        'actor_label',
        'event',
        'subject_type',
        'subject_id',
        'subject_label',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'event' => ActivityLogEvent::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
