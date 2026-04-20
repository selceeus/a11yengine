<?php

namespace App\Models;

use App\Enums\FindingSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfViolation extends Model
{
    protected $fillable = [
        'pdf_document_id',
        'rule_key',
        'severity',
        'wcag_criteria',
        'description',
        'element_context',
        'page_number',
    ];

    protected function casts(): array
    {
        return [
            'severity' => FindingSeverity::class,
            'page_number' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_document_id');
    }
}
