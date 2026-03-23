<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\IssueLink;
use Illuminate\Support\Facades\Crypt;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'property_id',
        'provider',
        'name',
        'credentials',
        'settings',
        'status',
        'error_message',
        'last_synced_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'status' => IntegrationStatus::class,
            'settings' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getCredentialsAttribute(string $value): array
    {
        return json_decode(Crypt::decryptString($value), true) ?? [];
    }

    public function setCredentialsAttribute(array $value): void
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function issueLinks(): HasMany
    {
        return $this->hasMany(IssueLink::class);
    }
}
