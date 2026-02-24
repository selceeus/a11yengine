<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agency_id',
        'name',
        'domain',
        'status',
    ];

    protected static function booted()
    {
        static::addGlobalScope('agency', function (Builder $builder) {
            if (app()->bound('currentAgency')) {
                $builder->where('agency_id', app('currentAgency')->id);
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
