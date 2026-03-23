<?php

namespace App\Models;

use App\Enums\ApiKeyScope;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'created_by',
        'name',
        'key_prefix',
        'token_hash',
        'scopes',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array{plaintext: string, hash: string, prefix: string}
     */
    public static function generateToken(): array
    {
        $secret = Str::random(40);
        $prefix = 'cbda_';
        $plaintext = $prefix.$secret;
        $hash = hash('sha256', $plaintext);

        return [
            'plaintext' => $plaintext,
            'hash' => $hash,
            'prefix' => substr($plaintext, 0, 12).'...',
        ];
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasScope(ApiKeyScope $scope): bool
    {
        return in_array($scope->value, $this->scopes ?? [], strict: true);
    }
}
