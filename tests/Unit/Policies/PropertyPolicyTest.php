<?php

use App\Models\Property;
use App\Models\User;
use App\Policies\PropertyPolicy;

function propertyUser(int $agencyId): User
{
    $user = new User;
    $user->agency_id = $agencyId;

    return $user;
}

function propertyModel(int $agencyId): Property
{
    $property = new Property;
    $property->agency_id = $agencyId;

    return $property;
}

$policy = new PropertyPolicy;

// ─── viewAny / create ────────────────────────────────────────────────────────

it('allows any authenticated user to view the property list', function () use ($policy): void {
    expect($policy->viewAny(propertyUser(1)))->toBeTrue();
});

it('allows any authenticated user to create a property', function () use ($policy): void {
    expect($policy->create(propertyUser(1)))->toBeTrue();
});

// ─── view ────────────────────────────────────────────────────────────────────

it('allows a user to view a property belonging to their agency', function () use ($policy): void {
    expect($policy->view(propertyUser(1), propertyModel(1)))->toBeTrue();
});

it('denies a user from viewing a property belonging to another agency', function () use ($policy): void {
    expect($policy->view(propertyUser(1), propertyModel(2)))->toBeFalse();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows a user to update a property belonging to their agency', function () use ($policy): void {
    expect($policy->update(propertyUser(1), propertyModel(1)))->toBeTrue();
});

it('denies a user from updating a property belonging to another agency', function () use ($policy): void {
    expect($policy->update(propertyUser(1), propertyModel(2)))->toBeFalse();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows a user to delete a property belonging to their agency', function () use ($policy): void {
    expect($policy->delete(propertyUser(1), propertyModel(1)))->toBeTrue();
});

it('denies a user from deleting a property belonging to another agency', function () use ($policy): void {
    expect($policy->delete(propertyUser(1), propertyModel(2)))->toBeFalse();
});
