<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskSnapshot extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agency_id',
        'organization_id',
        'total_risk_score',
        'open_issue_count',
        'snapshot_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'total_risk_score' => 'integer',
            'open_issue_count' => 'integer',
            'snapshot_date' => 'date',
            'created_at' => 'datetime',
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
}
