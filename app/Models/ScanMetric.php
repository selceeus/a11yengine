<?php

namespace App\Models;

use App\Domain\Scans\RecordScanMetrics;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanMetric extends Model
{
    use HasFactory;

    /**
     * Metrics are immutable — we track insertion time only.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'agency_id',
        'scan_id',
        'page_id',
        'metric_name',
        'metric_value',
        'metric_source',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'metric_value' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ScanPage::class, 'page_id');
    }

    /**
     * Bulk-insert a set of named metrics for a single page.
     *
     * Delegates to the RecordScanMetrics domain service.
     *
     * @param  array<string, int|float>  $metrics  Keyed by metric name, e.g. ['accessibility_issue_count' => 14]
     */
    public static function recordBulk(Scan $scan, ?ScanPage $page, array $metrics, string $source): void
    {
        app(RecordScanMetrics::class)->record($scan, $page, $metrics, $source);
    }
}
