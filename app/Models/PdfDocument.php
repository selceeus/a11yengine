<?php

namespace App\Models;

use App\Enums\PdfScanStatus;
use App\Models\Scopes\TenantScope;
use Database\Factories\PdfDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdfDocument extends Model
{
    /** @use HasFactory<PdfDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'property_id',
        'agency_id',
        'url',
        'filename',
        'status',
        'violation_count',
        'error_message',
        'scanned_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'status' => PdfScanStatus::class,
            'violation_count' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(PdfViolation::class);
    }
}
