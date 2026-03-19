<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Api\AgencyGovernanceReportController;
use App\Http\Controllers\Api\AgencyIssueSummaryController;
use App\Http\Controllers\Api\AgencyOrgRiskTrendsController;
use App\Http\Controllers\Api\AgencyScanActivityController;
use App\Http\Controllers\Api\AgencyTopRiskPropertiesController;
use App\Http\Controllers\Api\AssignIssueController;
use App\Http\Controllers\Api\GenerateIssueClustersController;
use App\Http\Controllers\Api\GenerateRiskAdvisoryController;
use App\Http\Controllers\Api\PropertyIssueClustersController;
use App\Http\Controllers\Api\PropertyRiskAdvisoryController;
use App\Http\Controllers\Api\PropertyRiskTrendsController;
use App\Http\Controllers\Api\PropertyScanActivityController;
use App\Http\Controllers\Api\RiskDashboardController;
use App\Http\Controllers\Api\RiskMapController;
use App\Http\Controllers\Api\ScanOverviewController;
use App\Http\Controllers\Api\ScheduledScanController;
use App\Http\Controllers\Api\UserAssignedIssuesController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueClusterController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RiskAdvisoryController;
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

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

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
    Route::post('issues/{issue}/remediation', [IssueController::class, 'generateRemediation'])->name('issues.remediation.generate');

    Route::get('/api/sites/{site}/risk-dashboard', RiskDashboardController::class)
        ->middleware('auth')
        ->name('api.sites.risk-dashboard');

    Route::get('/api/sites/{site}/risk-map', RiskMapController::class)
        ->middleware('auth')
        ->name('api.sites.risk-map');

    Route::get('scans', [ScanController::class, 'index'])->name('scans.index');
    Route::post('scans', [ScanController::class, 'store'])->name('scans.store');
    Route::get('scans/{scan}', [ScanController::class, 'show'])->name('scans.show');

    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::get('team/members/create', [TeamController::class, 'create'])->name('team.members.create');
    Route::post('team/members', [TeamController::class, 'store'])->name('team.members.store');
    Route::get('team/members/{user}/edit', [TeamController::class, 'edit'])->name('team.members.edit');
    Route::patch('team/members/{user}', [TeamController::class, 'update'])->name('team.members.update');
    Route::patch('team/members/{user}/password', [TeamController::class, 'updatePassword'])->name('team.members.password');
    Route::patch('team/members/{user}/role', [TeamController::class, 'updateRole'])->name('team.members.role');
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

    Route::get('api/properties/{property}/scans/activity', PropertyScanActivityController::class)
        ->name('api.properties.scans.activity');

    Route::get('api/properties/{property}/risk-trends', PropertyRiskTrendsController::class)
        ->name('api.properties.risk-trends');

    Route::post('api/issues/{issue}/assign', AssignIssueController::class)
        ->name('api.issues.assign');

    Route::get('api/users/{user}/issues', UserAssignedIssuesController::class)
        ->name('api.users.issues');

    Route::get('api/scans/{scan}/overview', ScanOverviewController::class)
        ->name('api.scans.overview');

    Route::post('api/properties/{property}/scheduled-scan', [ScheduledScanController::class, 'store'])
        ->name('api.properties.scheduled-scan.store');

    Route::put('api/properties/{property}/scheduled-scan/{scheduledScan}', [ScheduledScanController::class, 'update'])
        ->name('api.properties.scheduled-scan.update');

    Route::delete('api/properties/{property}/scheduled-scan/{scheduledScan}', [ScheduledScanController::class, 'destroy'])
        ->name('api.properties.scheduled-scan.destroy');

    Route::get('api/properties/{property}/audits/trend', \App\Http\Controllers\Api\AuditTrendController::class)
        ->name('api.properties.audits.trend');

    Route::get('api/properties/{property}/issue-clusters', PropertyIssueClustersController::class)
        ->name('api.properties.issue-clusters');

    Route::post('api/properties/{property}/issue-clusters/generate', GenerateIssueClustersController::class)
        ->name('api.properties.issue-clusters.generate');

    Route::get('audits', [AuditController::class, 'index'])->name('audits.index');
    Route::post('audits', [AuditController::class, 'store'])->name('audits.store');
    Route::get('audits/dashboard', [AuditController::class, 'dashboard'])->name('audits.dashboard');
    Route::get('audits/{audit}', [AuditController::class, 'show'])->name('audits.show');
    Route::delete('audits/{audit}', [AuditController::class, 'destroy'])->name('audits.destroy');
    Route::get('audits/{audit}/export/{format}', [AuditController::class, 'export'])
        ->name('audits.export')
        ->where('format', 'json|csv|pdf');

    Route::get('issue-clusters', [IssueClusterController::class, 'index'])->name('issue-clusters.index');

    Route::get('api/properties/{property}/risk-advisory', PropertyRiskAdvisoryController::class)
        ->name('api.properties.risk-advisory');

    Route::post('api/properties/{property}/risk-advisory/generate', GenerateRiskAdvisoryController::class)
        ->name('api.properties.risk-advisory.generate');

    Route::get('risk-advisory', [RiskAdvisoryController::class, 'index'])->name('risk-advisory.index');
});

Route::middleware('guest')->group(function (): void {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
