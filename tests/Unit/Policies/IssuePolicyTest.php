<?php

use App\Models\Issue;
use App\Models\User;
use App\Policies\IssuePolicy;

function issueUser(int $agencyId): User
{
    $user = new User;
    $user->agency_id = $agencyId;

    return $user;
}

function issueModel(int $agencyId): Issue
{
    $issue = new Issue;
    $issue->agency_id = $agencyId;

    return $issue;
}

$policy = new IssuePolicy;

// ─── viewAny ─────────────────────────────────────────────────────────────────

it('allows any authenticated user to view the issues list', function () use ($policy): void {
    expect($policy->viewAny(issueUser(1)))->toBeTrue();
});

// ─── view ────────────────────────────────────────────────────────────────────

it('allows a user to view an issue belonging to their agency', function () use ($policy): void {
    expect($policy->view(issueUser(1), issueModel(1)))->toBeTrue();
});

it('denies a user from viewing an issue belonging to another agency', function () use ($policy): void {
    expect($policy->view(issueUser(1), issueModel(2)))->toBeFalse();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows a user to update an issue belonging to their agency', function () use ($policy): void {
    expect($policy->update(issueUser(1), issueModel(1)))->toBeTrue();
});

it('denies a user from updating an issue belonging to another agency', function () use ($policy): void {
    expect($policy->update(issueUser(1), issueModel(2)))->toBeFalse();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows a user to delete an issue belonging to their agency', function () use ($policy): void {
    expect($policy->delete(issueUser(1), issueModel(1)))->toBeTrue();
});

it('denies a user from deleting an issue belonging to another agency', function () use ($policy): void {
    expect($policy->delete(issueUser(1), issueModel(2)))->toBeFalse();
});
