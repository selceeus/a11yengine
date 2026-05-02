<?php

namespace App\Models;

use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'billing_email',
        'mcp_token_hash',
    ];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class);
    }
}
