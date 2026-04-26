<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\AgencyGovernanceReportController;
use App\Http\Controllers\Api\AgencyIssueSummaryController;
use App\Http\Controllers\Api\AgencyOrgRiskTrendsController;
use App\Http\Controllers\Api\AgencyScanActivityController;
use App\Http\Controllers\Api\AgencyTopRiskPropertiesController;
use App\Http\Controllers\Api\AssignIssueController;
use App\Http\Controllers\Api\BulkIssueController;
use App\Http\Controllers\Api\GenerateContentAuditController;
use App\Http\Controllers\Api\GenerateGovernanceReportController;
use App\Http\Controllers\Api\GenerateIssueClustersController;
use App\Http\Controllers\Api\GenerateRiskAdvisoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrgIssueSummaryController;
use App\Http\Controllers\Api\OrgRiskTrendsController;
use App\Http\Controllers\Api\OrgScanActivityController;
use App\Http\Controllers\Api\OrgTopRiskPropertiesController;
use App\Http\Controllers\Api\PropertyContentAuditController;
use App\Http\Controllers\Api\PropertyGovernanceReportController;
use App\Http\Controllers\Api\PropertyIssueClustersController;
use App\Http\Controllers\Api\PropertyRiskAdvisoryController;
use App\Http\Controllers\Api\PropertyRiskTrendsController;
use App\Http\Controllers\Api\PropertyScanActivityController;
use App\Http\Controllers\Api\RiskDashboardController;
use App\Http\Controllers\Api\RiskMapController;
use App\Http\Controllers\Api\ScanOverviewController;
use App\Http\Controllers\Api\ScheduledScanController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UserAssignedIssuesController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\ContentAuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GovernanceReportController;
use App\Http\Controllers\IssueClusterController;
use App\Http\Controllers\IssueCommentController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PdfDocumentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RiskAdvisoryController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SendInvitationController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
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
    Route::post('issues/{issue}/comments', [IssueCommentController::class, 'store'])->name('issues.comments.store');

    Route::get('/api/sites/{site}/risk-dashboard', RiskDashboardController::class)
        ->middleware('auth')
        ->name('api.sites.risk-dashboard');

    Route::get('/api/sites/{site}/risk-map', RiskMapController::class)
        ->middleware('auth')
        ->name('api.sites.risk-map');

    Route::get('scans', [ScanController::class, 'index'])->name('scans.index');
    Route::post('scans', [ScanController::class, 'store'])->name('scans.store');
    Route::get('scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
    Route::delete('scans/{scan}', [ScanController::class, 'destroy'])->name('scans.destroy');
    Route::get('scans/{scan}/diff', [\App\Http\Controllers\ScanDiffController::class, 'show'])->name('scans.diff');

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

    Route::post('api/issues/bulk', BulkIssueController::class.'@update')
        ->name('api.issues.bulk');

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

    Route::patch('api/properties/{property}/scheduled-scan/{scheduledScan}/toggle', [ScheduledScanController::class, 'toggle'])
        ->name('api.properties.scheduled-scan.toggle');

    Route::get('api/scheduled-scans', [ScheduledScanController::class, 'index'])
        ->name('api.scheduled-scans.index');

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
    Route::get('issue-clusters/{issueCluster}', [IssueClusterController::class, 'show'])->name('issue-clusters.show');

    Route::get('api/properties/{property}/risk-advisory', PropertyRiskAdvisoryController::class)
        ->name('api.properties.risk-advisory');

    Route::post('api/properties/{property}/risk-advisory/generate', GenerateRiskAdvisoryController::class)
        ->name('api.properties.risk-advisory.generate');

    Route::get('risk-advisory', [RiskAdvisoryController::class, 'index'])->name('risk-advisory.index');

    Route::get('api/properties/{property}/content-audit', PropertyContentAuditController::class)
        ->name('api.properties.content-audit');

    Route::post('api/properties/{property}/content-audit/generate', GenerateContentAuditController::class)
        ->name('api.properties.content-audit.generate');

    Route::get('content-audit', [ContentAuditController::class, 'index'])->name('content-audit.index');

    Route::get('api/properties/{property}/governance-report', PropertyGovernanceReportController::class)
        ->name('api.properties.governance-report');

    Route::post('api/properties/{property}/governance-report/generate', GenerateGovernanceReportController::class)
        ->name('api.properties.governance-report.generate');

    Route::get('governance', [GovernanceReportController::class, 'index'])->name('governance.index');
    Route::post('governance', [GovernanceReportController::class, 'store'])->name('governance.store');
    Route::get('governance/{report}', [GovernanceReportController::class, 'show'])->name('governance.show');
    Route::delete('governance/{report}', [GovernanceReportController::class, 'destroy'])->name('governance.destroy');
    Route::get('governance/{report}/export/{format}', [GovernanceReportController::class, 'export'])
        ->name('governance.export')
        ->where('format', 'json|csv|pdf');

    Route::get('risk-advisory/{riskAdvisory}', [RiskAdvisoryController::class, 'show'])->name('risk-advisory.show');
    Route::get('risk-advisory/{riskAdvisory}/export/{format}', [RiskAdvisoryController::class, 'export'])
        ->name('risk-advisory.export')
        ->where('format', 'json|csv|pdf');

    Route::get('content-audit/{contentAudit}', [ContentAuditController::class, 'show'])->name('content-audit.show');
    Route::get('content-audit/{contentAudit}/export/{format}', [ContentAuditController::class, 'export'])
        ->name('content-audit.export')
        ->where('format', 'json|csv|pdf');

    Route::get('pdf-documents/{pdfDocument}', [PdfDocumentController::class, 'show'])->name('pdf-documents.show');

    Route::get('scans/{scan}/export/{format}', [ScanController::class, 'export'])
        ->name('scans.export')
        ->where('format', 'json|csv');

    Route::get('api/organizations/{organization}/issues/summary', OrgIssueSummaryController::class)
        ->name('api.organizations.issues.summary');

    Route::get('api/organizations/{organization}/scans/activity', OrgScanActivityController::class)
        ->name('api.organizations.scans.activity');

    Route::get('api/organizations/{organization}/risk-trends', OrgRiskTrendsController::class)
        ->name('api.organizations.risk-trends');

    Route::get('api/organizations/{organization}/properties/top-risk', OrgTopRiskPropertiesController::class)
        ->name('api.organizations.properties.top-risk');

    Route::get('api/search', SearchController::class)
        ->name('api.search');

    Route::get('api/notifications', [NotificationController::class, 'index'])
        ->name('api.notifications.index');
    Route::patch('api/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('api.notifications.read');
    Route::post('api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('api.notifications.read-all');

    Route::get('api/activity-feed', ActivityFeedController::class)
        ->name('api.activity-feed');
});

Route::middleware('guest')->group(function (): void {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
