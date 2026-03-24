<?php

namespace App\Models;

use App\Enums\ContentAuditStatus;
use App\Models\Scopes\TenantScope;
use Database\Factories\ContentAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAudit extends Model
{
    /** @use HasFactory<ContentAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'status',
        'content_issues',
        'total_issues',
        'pages_analyzed',
        'reading_metrics',
        'avg_reading_level',
        'avg_reading_time_seconds',
        'prompt_context',
        'raw_ai_response',
        'error_message',
        'generated_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'status' => ContentAuditStatus::class,
            'content_issues' => 'array',
            'total_issues' => 'integer',
            'pages_analyzed' => 'integer',
            'reading_metrics' => 'array',
            'avg_reading_time_seconds' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
