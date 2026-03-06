<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Api\AgencyGovernanceReportController;
use App\Http\Controllers\Api\AgencyIssueSummaryController;
use App\Http\Controllers\Api\AgencyOrgRiskTrendsController;
use App\Http\Controllers\Api\AgencyScanActivityController;
use App\Http\Controllers\Api\AgencyTopRiskPropertiesController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SendInvitationController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');

    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::patch('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

    Route::get('issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('issues/{issue}', [IssueController::class, 'show'])->name('issues.show');
    Route::patch('issues/{issue}', [IssueController::class, 'update'])->name('issues.update');

    Route::get('scans', [ScanController::class, 'index'])->name('scans.index');
    Route::post('scans', [ScanController::class, 'store'])->name('scans.store');
    Route::get('scans/{scan}', [ScanController::class, 'show'])->name('scans.show');

    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::delete('team/members/{user}', [TeamController::class, 'destroyMember'])->name('team.members.destroy');
    Route::delete('team/invitations/{invitation}', [TeamController::class, 'destroyInvitation'])->name('team.invitations.destroy');

    Route::post('invitations', SendInvitationController::class)->name('invitations.send');

    Route::get('api/agencies/{agency}/issues/summary', AgencyIssueSummaryController::class)
        ->name('api.agencies.issues.summary');

    Route::get('api/agencies/{agency}/scans/activity', AgencyScanActivityController::class)
        ->name('api.agencies.scans.activity');

    Route::get('api/agencies/{agency}/organizations/risk-trends', AgencyOrgRiskTrendsController::class)
        ->name('api.agencies.organizations.risk-trends');

    Route::get('api/agencies/{agency}/properties/top-risk', AgencyTopRiskPropertiesController::class)
        ->name('api.agencies.properties.top-risk');

    Route::get('api/agencies/{agency}/governance-report', AgencyGovernanceReportController::class)
        ->name('api.agencies.governance-report');
});

Route::middleware('guest')->group(function (): void {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
