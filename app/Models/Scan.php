<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'status',
        'pages_scanned',
        'pages_discovered',
        'total_violations',
        'raw_output_path',
        'error_message',
        'started_at',
        'completed_at',
        'raw_summary',
        'scan_config',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'status' => ScanStatus::class,
            'pages_scanned' => 'integer',
            'pages_discovered' => 'integer',
            'total_violations' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'raw_summary' => 'array',
            'scan_config' => 'array',
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

    public function scanPages(): HasMany
    {
        return $this->hasMany(ScanPage::class);
    }

    public function pdfDocuments(): HasMany
    {
        return $this->hasMany(PdfDocument::class);
    }
}
