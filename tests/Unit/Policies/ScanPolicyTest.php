<?php

use App\Models\Scan;
use App\Models\User;
use App\Policies\ScanPolicy;

function scanUser(int $agencyId): User
{
    $user = new User;
    $user->agency_id = $agencyId;

    return $user;
}

function scanModel(int $agencyId): Scan
{
    $scan = new Scan;
    $scan->agency_id = $agencyId;

    return $scan;
}

$policy = new ScanPolicy;

// ─── viewAny ─────────────────────────────────────────────────────────────────

it('allows any authenticated user to view the scan list', function () use ($policy): void {
    expect($policy->viewAny(scanUser(1)))->toBeTrue();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('allows any authenticated user to create a scan', function () use ($policy): void {
    expect($policy->create(scanUser(1)))->toBeTrue();
});

// ─── view ────────────────────────────────────────────────────────────────────

it('allows a user to view a scan belonging to their agency', function () use ($policy): void {
    expect($policy->view(scanUser(1), scanModel(1)))->toBeTrue();
});

it('denies a user from viewing a scan belonging to another agency', function () use ($policy): void {
    expect($policy->view(scanUser(1), scanModel(2)))->toBeFalse();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows a user to delete a scan belonging to their agency', function () use ($policy): void {
    expect($policy->delete(scanUser(1), scanModel(1)))->toBeTrue();
});

it('denies a user from deleting a scan belonging to another agency', function () use ($policy): void {
    expect($policy->delete(scanUser(1), scanModel(2)))->toBeFalse();
});
