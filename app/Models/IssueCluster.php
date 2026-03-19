<?php

namespace App\Models;

use App\Enums\ClusterStatus;
use App\Models\Scopes\TenantScope;
use Database\Factories\IssueClusterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueCluster extends Model
{
    /** @use HasFactory<IssueClusterFactory> */
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'status',
        'clusters',
        'total_clusters',
        'open_issues_analyzed',
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
            'status' => ClusterStatus::class,
            'clusters' => 'array',
            'total_clusters' => 'integer',
            'open_issues_analyzed' => 'integer',
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
