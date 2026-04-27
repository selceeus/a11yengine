<?php

namespace App\Models;

use App\Enums\AccessReviewStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessReview extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agency_id',
        'period',
        'status',
        'due_at',
        'completed_at',
        'completed_by',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => AccessReviewStatus::class,
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === AccessReviewStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === AccessReviewStatus::Completed;
    }
}
