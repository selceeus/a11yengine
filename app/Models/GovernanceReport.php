<?php

namespace App\Models;

use App\Enums\GovernanceReportStatus;
use App\Models\Scopes\TenantScope;
use Database\Factories\GovernanceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceReport extends Model
{
    /** @use HasFactory<GovernanceReportFactory> */
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'report_scope',
        'period_from',
        'period_to',
        'status',
        'executive_narrative',
        'risk_trend',
        'severity_breakdown',
        'remediation_progress',
        'compliance_status',
        'legal_risk_rating',
        'legal_precedents',
        'recommendations',
        'summary_cards',
        'prompt_context',
        'raw_ai_response',
        'error_message',
        'generated_at',
        'is_scheduled',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'status' => GovernanceReportStatus::class,
            'risk_trend' => 'array',
            'severity_breakdown' => 'array',
            'remediation_progress' => 'array',
            'compliance_status' => 'array',
            'legal_precedents' => 'array',
            'recommendations' => 'array',
            'summary_cards' => 'array',
            'period_from' => 'date',
            'period_to' => 'date',
            'generated_at' => 'datetime',
            'is_scheduled' => 'boolean',
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
