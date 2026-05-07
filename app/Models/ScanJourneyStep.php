<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanJourneyStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_journey_id',
        'position',
        'label',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(ScanJourney::class, 'scan_journey_id');
    }
}
