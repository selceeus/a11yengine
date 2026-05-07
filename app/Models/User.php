<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole as UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
        'avatar_path',
        'password',
        'must_change_password',
    ];

    public function getAvatarAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

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

    public function canEditProperty(int $propertyId): bool
    {
        if ($this->canManageProperty($propertyId)) {
            return true;
        }

        if ($this->roles()->where('role', UserRoleEnum::Editor->value)->where('property_id', $propertyId)->exists()) {
            return true;
        }

        $property = Property::withoutGlobalScopes()->where('id', $propertyId)->first(['organization_id', 'agency_id']);

        if ($property === null) {
            return false;
        }

        if ($this->roles()->where('role', UserRoleEnum::Editor->value)->where('organization_id', $property->organization_id)->exists()) {
            return true;
        }

        return $this->roles()->where('role', UserRoleEnum::Editor->value)->where('agency_id', $property->agency_id)->exists();
    }

    public function canEditOrg(int $orgId): bool
    {
        if ($this->canManageOrg($orgId)) {
            return true;
        }

        if ($this->roles()->where('role', UserRoleEnum::Editor->value)->where('organization_id', $orgId)->exists()) {
            return true;
        }

        $agencyId = Organization::withoutGlobalScopes()->where('id', $orgId)->value('agency_id');

        return $agencyId !== null
            && $this->roles()->where('role', UserRoleEnum::Editor->value)->where('agency_id', $agencyId)->exists();
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
