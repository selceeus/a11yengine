<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyRiskSnapshot extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agency_id',
        'risk_score',
        'open_issue_count',
        'snapshot_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'open_issue_count' => 'integer',
            'snapshot_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
