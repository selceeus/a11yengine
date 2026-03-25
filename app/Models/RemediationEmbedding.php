<?php

namespace App\Models;

use App\Casts\VectorCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemediationEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'rule_key',
        'wcag_criteria',
        'description',
        'resolution',
        'outcome',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => VectorCast::class,
            'metadata' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
