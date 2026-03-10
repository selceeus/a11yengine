<?php

use App\Http\Controllers\Api\AssignIssueController;
use App\Http\Controllers\Api\OrganizationGovernanceReportController;
use App\Http\Controllers\Api\OrganizationRiskBreakdownController;
use App\Http\Controllers\Api\OrganizationRiskController;
use App\Http\Controllers\Api\OrganizationRiskSummaryController;
use App\Http\Controllers\Api\OrganizationUserImpactController;
use App\Http\Controllers\Api\RiskDashboardController;
use App\Http\Controllers\Api\RiskMapController;
use Illuminate\Support\Facades\Route;

Route::post('issues/{issue}/assign', AssignIssueController::class)
    ->middleware('auth')
    ->name('api.issues.assign');

Route::post('organizations/{organizationId}/risk-snapshot', OrganizationRiskController::class)
    ->name('api.organizations.risk-snapshot');

Route::get('organizations/{organizationId}/risk-summary', OrganizationRiskSummaryController::class)
    ->middleware('auth')
    ->name('api.organizations.risk-summary');

Route::get('organizations/{organizationId}/governance-summary', OrganizationGovernanceReportController::class)
    ->name('api.organizations.governance-summary');

Route::get('organizations/{organizationId}/risk-breakdown', OrganizationRiskBreakdownController::class)
    ->name('api.organizations.risk-breakdown');

Route::get('organizations/{organizationId}/user-impact', OrganizationUserImpactController::class)
    ->name('api.organizations.user-impact');

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
// Route::prefix('{tenant}')
//     ->middleware('tenant')
//     ->name('tenant.')
//     ->group(function (): void {
//         Route::get('risk-summary', AgencyRiskSummaryController::class)
//             ->name('risk-summary');
//     });
