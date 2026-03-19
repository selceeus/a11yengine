<?php

namespace App\Models;

use App\Enums\RiskAdvisoryStatus;
use App\Models\Scopes\TenantScope;
use Database\Factories\RiskAdvisoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAdvisory extends Model
{
    /** @use HasFactory<RiskAdvisoryFactory> */
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'status',
        'priorities',
        'total_recommendations',
        'issues_analyzed',
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
            'status' => RiskAdvisoryStatus::class,
            'priorities' => 'array',
            'total_recommendations' => 'integer',
            'issues_analyzed' => 'integer',
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
