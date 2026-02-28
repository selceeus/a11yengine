<?php

use App\Http\Controllers\Api\OrganizationGovernanceReportController;
use App\Http\Controllers\Api\OrganizationRiskBreakdownController;
use App\Http\Controllers\Api\OrganizationRiskController;
use App\Http\Controllers\Api\OrganizationRiskSummaryController;
use App\Http\Controllers\Api\OrganizationUserImpactController;
use Illuminate\Support\Facades\Route;

Route::post('organizations/{organizationId}/risk-snapshot', OrganizationRiskController::class)
    ->name('api.organizations.risk-snapshot');

Route::get('organizations/{organizationId}/risk-summary', OrganizationRiskSummaryController::class)
    ->name('api.organizations.risk-summary');

Route::get('organizations/{organizationId}/governance-summary', OrganizationGovernanceReportController::class)
    ->name('api.organizations.governance-summary');

Route::get('organizations/{organizationId}/risk-breakdown', OrganizationRiskBreakdownController::class)
    ->name('api.organizations.risk-breakdown');

Route::get('organizations/{organizationId}/user-impact', OrganizationUserImpactController::class)
    ->name('api.organizations.user-impact');
