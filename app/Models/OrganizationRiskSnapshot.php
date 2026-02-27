<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRiskSnapshot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'risk_score',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
