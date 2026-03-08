<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole as UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'agency_id',
        'name',
        'email',
        'password',
        'must_change_password',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function hasRole(string|UserRoleEnum $role, ?int $scopeId = null): bool
    {
        $roleValue = $role instanceof UserRoleEnum ? $role->value : $role;

        return $this->roles()
            ->where('role', $roleValue)
            ->when($scopeId !== null, fn ($q) => $q->where(function ($q) use ($scopeId) {
                $q->where('agency_id', $scopeId)
                    ->orWhere('organization_id', $scopeId)
                    ->orWhere('property_id', $scopeId);
            }))
            ->exists();
    }

    public function isSuperUser(): bool
    {
        return $this->roles()->where('role', UserRoleEnum::SuperUser->value)->exists();
    }

    public function canManageAgency(int $agencyId): bool
    {
        return $this->isSuperUser()
            || $this->roles()
                ->where('role', UserRoleEnum::AgencyAdmin->value)
                ->where('agency_id', $agencyId)
                ->exists();
    }

    public function canManageOrg(int $orgId): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        if ($this->roles()->where('role', UserRoleEnum::OrgAdmin->value)->where('organization_id', $orgId)->exists()) {
            return true;
        }

        $agencyId = Organization::withoutGlobalScopes()->where('id', $orgId)->value('agency_id');

        return $agencyId !== null && $this->canManageAgency($agencyId);
    }

    public function canManageProperty(int $propertyId): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        if ($this->roles()->where('role', UserRoleEnum::PropAdmin->value)->where('property_id', $propertyId)->exists()) {
            return true;
        }

        $orgId = Property::withoutGlobalScopes()->where('id', $propertyId)->value('organization_id');

        return $orgId !== null && $this->canManageOrg($orgId);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }
}
