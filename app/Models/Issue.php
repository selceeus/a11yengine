<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'rule_key',
        'severity',
        'status',
        'occurrence_count',
        'risk_weight',
        'first_detected_at',
        'last_detected_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
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

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
