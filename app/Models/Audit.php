<?php

namespace App\Models;

use App\Enums\AuditStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'title',
        'status',
        'source_scan_ids',
        'prompt_context',
        'raw_ai_response',
        'executive_summary',
        'compliance_status',
        'top_risks',
        'issue_details',
        'remediations',
        'summary_statistics',
        'overall_score',
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
            'status' => AuditStatus::class,
            'source_scan_ids' => 'array',
            'compliance_status' => 'array',
            'top_risks' => 'array',
            'issue_details' => 'array',
            'remediations' => 'array',
            'summary_statistics' => 'array',
            'overall_score' => 'integer',
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
