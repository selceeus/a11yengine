<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->otherAgency = Agency::factory()->create();

    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);

    $this->otherAdmin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->otherAgency->id)
        ->create(['agency_id' => $this->otherAgency->id]);

    $this->org = Organization::factory()->create(['agency_id' => $this->agency->id]);
});

// ── user-impact ───────────────────────────────────────────────────────────────

describe('GET api/organizations/{id}/user-impact', function (): void {
    it('returns 401 for unauthenticated requests', function (): void {
        $this->getJson(route('api.organizations.user-impact', $this->org->id))
            ->assertUnauthorized();
    });

    it('returns 403 when the authenticated user belongs to a different agency', function (): void {
        $this->actingAs($this->otherAdmin)
            ->getJson(route('api.organizations.user-impact', $this->org->id))
            ->assertForbidden();
    });
});

// ── risk-summary ─────────────────────────────────────────────────────────────

describe('GET api/organizations/{id}/risk-summary', function (): void {
    it('returns 401 for unauthenticated requests', function (): void {
        $this->getJson(route('api.organizations.risk-summary', $this->org->id))
            ->assertUnauthorized();
    });

    it('returns 403 when the authenticated user belongs to a different agency', function (): void {
        $this->actingAs($this->otherAdmin)
            ->getJson(route('api.organizations.risk-summary', $this->org->id))
            ->assertForbidden();
    });
});

// ── risk-breakdown ────────────────────────────────────────────────────────────

describe('GET api/organizations/{id}/risk-breakdown', function (): void {
    it('returns 401 for unauthenticated requests', function (): void {
        $this->getJson(route('api.organizations.risk-breakdown', $this->org->id))
            ->assertUnauthorized();
    });

    it('returns 403 when the authenticated user belongs to a different agency', function (): void {
        $this->actingAs($this->otherAdmin)
            ->getJson(route('api.organizations.risk-breakdown', $this->org->id))
            ->assertForbidden();
    });
});

// ── governance-summary ────────────────────────────────────────────────────────

describe('GET api/organizations/{id}/governance-summary', function (): void {
    it('returns 401 for unauthenticated requests', function (): void {
        $this->getJson(route('api.organizations.governance-summary', $this->org->id))
            ->assertUnauthorized();
    });

    it('returns 403 when the authenticated user belongs to a different agency', function (): void {
        $this->actingAs($this->otherAdmin)
            ->getJson(route('api.organizations.governance-summary', $this->org->id))
            ->assertForbidden();
    });
});

// ── risk-snapshot (POST — write) ──────────────────────────────────────────────

describe('POST api/organizations/{id}/risk-snapshot', function (): void {
    it('returns 401 for unauthenticated requests', function (): void {
        $this->postJson(route('api.organizations.risk-snapshot', $this->org->id))
            ->assertUnauthorized();
    });

    it('returns 403 when the authenticated user belongs to a different agency', function (): void {
        $this->actingAs($this->otherAdmin)
            ->postJson(route('api.organizations.risk-snapshot', $this->org->id))
            ->assertForbidden();
    });
});
