<?php

namespace App\Models;

use App\Domain\Scans\ScanConfig;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'name',
        'slug',
        'base_url',
        'status',
        'scan_config',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'scan_config' => 'array',
        ];
    }

    public function defaultScanConfig(): ScanConfig
    {
        return $this->scan_config
            ? ScanConfig::fromArray($this->scan_config)
            : new ScanConfig;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function scheduledScan(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ScheduledScan::class)->where('is_active', true)->latest();
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
