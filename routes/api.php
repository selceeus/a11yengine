<?php

use App\Http\Controllers\Api\AgencyRiskSummaryController;
use App\Http\Controllers\Api\IntegrationWebhookController;
use App\Http\Controllers\Api\OrganizationGovernanceReportController;
use App\Http\Controllers\Api\OrganizationRiskBreakdownController;
use App\Http\Controllers\Api\OrganizationRiskController;
use App\Http\Controllers\Api\OrganizationRiskSummaryController;
use App\Http\Controllers\Api\OrganizationUserImpactController;
use App\Http\Controllers\Api\TenantGovernanceSummaryController;
use App\Http\Controllers\Api\TenantIssueSummaryController;
use App\Http\Controllers\Api\TenantScanActivityController;
use App\Http\Controllers\Api\WordPressIssuesController;
use App\Http\Controllers\Api\WordPressPropertiesController;
use App\Http\Controllers\Api\WordPressRiskSummaryController;
use App\Http\Controllers\Api\WordPressScansController;
use Illuminate\Support\Facades\Route;

Route::post('organizations/{organizationId}/risk-snapshot', OrganizationRiskController::class)
    ->middleware('auth')
    ->name('api.organizations.risk-snapshot');

Route::get('organizations/{organizationId}/risk-summary', OrganizationRiskSummaryController::class)
    ->middleware('auth')
    ->name('api.organizations.risk-summary');

Route::get('organizations/{organizationId}/governance-summary', OrganizationGovernanceReportController::class)
    ->middleware('auth')
    ->name('api.organizations.governance-summary');

Route::get('organizations/{organizationId}/risk-breakdown', OrganizationRiskBreakdownController::class)
    ->middleware('auth')
    ->name('api.organizations.risk-breakdown');

Route::get('organizations/{organizationId}/user-impact', OrganizationUserImpactController::class)
    ->middleware('auth')
    ->name('api.organizations.user-impact');

// ---------------------------------------------------------------------------
// WordPress Plugin API
// ---------------------------------------------------------------------------
// Authenticated via scoped API key: Authorization: Bearer <key>
// The api.key middleware resolves the owning Agency and binds it to the
// container. All routes require the `wordpress` scope.
// ---------------------------------------------------------------------------
Route::prefix('wordpress')
    ->middleware(['api.key:wordpress', 'throttle:60,1'])
    ->name('wordpress.')
    ->group(function (): void {
        Route::get('properties', WordPressPropertiesController::class)
            ->name('properties');

        Route::get('properties/{propertySlug}/issues', WordPressIssuesController::class)
            ->name('properties.issues');

        Route::get('properties/{propertySlug}/risk-summary', WordPressRiskSummaryController::class)
            ->name('properties.risk-summary');

        Route::post('properties/{propertySlug}/scans', WordPressScansController::class)
            ->name('properties.scans');
    });

// ---------------------------------------------------------------------------
// Tenant-scoped routes
// ---------------------------------------------------------------------------
// The `tenant` middleware resolves the Agency from the {tenant} slug in the
// URL (or from the X-Tenant request header) and binds it to the container.
// Use these routes when you need per-agency context without authentication,
// e.g. public dashboards or machine-to-machine API clients.
//
// Controller injection example:
//
//   use App\Models\Agency;
//
//   public function __invoke(Agency $agency): JsonResponse
//   {
//       return response()->json([
//           'agency' => $agency->name,
//           'organizations' => $agency->organizations()->count(),
//       ]);
//   }
// ---------------------------------------------------------------------------
Route::prefix('{tenant}')
    ->middleware(['tenant', 'api.key:scans:read', 'throttle:120,1'])
    ->name('tenant.')
    ->group(function (): void {
        Route::get('risk-summary', AgencyRiskSummaryController::class)
            ->name('risk-summary');

        Route::get('scan-activity', TenantScanActivityController::class)
            ->name('scan-activity');

        Route::get('issues', TenantIssueSummaryController::class)
            ->name('issues');

        Route::get('governance-summary', TenantGovernanceSummaryController::class)
            ->name('governance-summary');
    });

Route::post('webhooks/integrations/{integration}', IntegrationWebhookController::class)
    ->name('api.webhooks.integrations');
