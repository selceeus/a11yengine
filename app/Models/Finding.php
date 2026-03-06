<?php

namespace App\Models;

use App\Enums\FindingSeverity;
use App\Enums\IssueStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'issue_id',
        'scan_id',
        'property_id',
        'rule_key',
        'fingerprint',
        'severity',
        'element_identifier',
        'page_url',
        'message',
        'detected_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (self $finding): void {
            $finding->fingerprint ??= $finding->computeFingerprint();
        });

        static::created(function (self $finding): void {
            $activeStatuses = array_map(
                fn (IssueStatus $s) => $s->value,
                IssueStatus::activeStatuses()
            );

            Issue::withoutGlobalScope(TenantScope::class)
                ->where('property_id', $finding->property_id)
                ->where('rule_key', $finding->rule_key)
                ->whereIn('status', $activeStatuses)
                ->each(fn (Issue $issue) => $issue->incrementOccurrence());
        });
    }

    public function computeFingerprint(): string
    {
        return sha1(
            $this->rule_key.'|'.($this->element_identifier ?? '').'|'.$this->page_url
        );
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

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
