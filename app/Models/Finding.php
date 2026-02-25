<?php

namespace App\Models;

use App\Enums\FindingSeverity;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'scan_id',
        'property_id',
        'rule_key',
        'severity',
        'element_identifier',
        'page_url',
        'message',
        'detected_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected function casts(): array
    {
        return [
            'severity' => FindingSeverity::class,
            'detected_at' => 'datetime',
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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
