<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ScheduledScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'organization_id',
        'property_id',
        'type',
        'frequency',
        'scheduled_at',
        'run_time',
        'run_day_of_week',
        'run_day_of_month',
        'next_run_at',
        'last_run_at',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
            'run_day_of_week' => 'integer',
            'run_day_of_month' => 'integer',
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

    public function computeNextRunAt(Carbon $from): Carbon
    {
        [$h, $min] = array_map('intval', explode(':', $this->run_time ?? '09:00'));
        $dom = $this->run_day_of_month ?? 1;

        return match ($this->frequency) {
            'daily' => $from->copy()->addDay()->setTime($h, $min, 0),
            'weekly' => $from->copy()->addWeek()->setTime($h, $min, 0),
            'monthly' => $this->computeNextMonthly($from, $h, $min, $dom),
            'quarterly' => $this->computeNextQuarterly($from, $h, $min, $dom),
            default => $from->copy()->addDay()->setTime($h, $min, 0),
        };
    }

    private function computeNextMonthly(Carbon $from, int $h, int $min, int $dom): Carbon
    {
        $next = $from->copy()->addMonthNoOverflow()->startOfMonth();

        return $next->setDay(min($dom, $next->daysInMonth))->setTime($h, $min, 0);
    }

    private function computeNextQuarterly(Carbon $from, int $h, int $min, int $dom): Carbon
    {
        $quarterMonths = [1, 4, 7, 10];
        $currentMonth = $from->month;
        $year = $from->year;

        $currentQStart = 1;
        foreach ($quarterMonths as $qm) {
            if ($qm <= $currentMonth) {
                $currentQStart = $qm;
            }
        }

        $idx = array_search($currentQStart, $quarterMonths);
        if ($idx === 3) {
            $nextQMonth = 1;
            $year++;
        } else {
            $nextQMonth = $quarterMonths[$idx + 1];
        }

        $next = Carbon::create($year, $nextQMonth, 1, 0, 0, 0);

        return $next->setDay(min($dom, $next->daysInMonth))->setTime($h, $min, 0);
    }
}
