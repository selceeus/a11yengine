<?php

namespace App\Models;

use App\Casts\VectorCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LawsuitEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_name',
        'filed_year',
        'industry',
        'violation_type',
        'wcag_criteria',
        'outcome',
        'settlement_amount',
        'summary',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'wcag_criteria' => 'array',
            'embedding' => VectorCast::class,
            'metadata' => 'array',
            'settlement_amount' => 'integer',
        ];
    }
}
