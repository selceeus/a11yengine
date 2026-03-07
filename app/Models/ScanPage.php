<?php

namespace App\Models;

use App\Enums\ScanPageStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'scan_id',
        'url',
        'violations_count',
        'status',
        'axe_completed',
        'lighthouse_completed',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'status' => ScanPageStatus::class,
            'violations_count' => 'integer',
            'axe_completed' => 'boolean',
            'lighthouse_completed' => 'boolean',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
