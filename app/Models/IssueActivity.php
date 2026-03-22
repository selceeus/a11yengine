<?php

namespace App\Models;

use App\Enums\IssueActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'issue_id',
        'user_id',
        'type',
        'body',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => IssueActivityType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
