<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'rule_key',
        'page_url',
        'severity',
        'wcag_category',
        'wcag_criteria',
        'description',
        'tags',
        'help_url',
        'status',
        'occurrence_count',
        'risk_weight',
        'first_detected_at',
        'last_detected_at',
        'resolved_at',
        'assigned_user_id',
        'resolution_notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'tags' => 'array',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignToUser(User $user): void
    {
        $this->assigned_user_id = $user->id;

        if ($this->status === IssueStatus::Open) {
            $this->status = IssueStatus::InProgress;
        }

        $this->save();
    }

    public function unassignUser(): void
    {
        $this->assigned_user_id = null;
        $this->save();
    }

    public function markResolved(?string $notes = null): void
    {
        $this->status = IssueStatus::Resolved;
        $this->resolved_at = now();
        $this->resolution_notes = $notes;
        $this->save();
    }

    public function incrementOccurrence(): void
    {
        $this->increment('occurrence_count');
    }
}
