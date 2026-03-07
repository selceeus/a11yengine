<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LighthouseResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'scan_id',
        'url',
        'performance_score',
        'accessibility_score',
        'best_practices_score',
        'seo_score',
        'first_contentful_paint',
        'largest_contentful_paint',
        'total_blocking_time',
        'cumulative_layout_shift',
        'raw_metrics',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'performance_score' => 'integer',
            'accessibility_score' => 'integer',
            'best_practices_score' => 'integer',
            'seo_score' => 'integer',
            'first_contentful_paint' => 'float',
            'largest_contentful_paint' => 'float',
            'total_blocking_time' => 'float',
            'cumulative_layout_shift' => 'float',
            'raw_metrics' => 'array',
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
}
