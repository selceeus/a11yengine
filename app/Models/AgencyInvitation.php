<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'email',
        'token',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->created_at->diffInDays(now()) < 7;
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->created_at->diffInDays(now()) >= 7;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
