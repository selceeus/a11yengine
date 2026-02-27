<?php

use App\Http\Controllers\Api\OrganizationGovernanceSummaryController;
use App\Http\Controllers\Api\OrganizationRiskController;
use App\Http\Controllers\Api\OrganizationRiskSummaryController;
use Illuminate\Support\Facades\Route;

Route::post('organizations/{organizationId}/risk-snapshot', OrganizationRiskController::class)
    ->name('api.organizations.risk-snapshot');

Route::get('organizations/{organizationId}/risk-summary', OrganizationRiskSummaryController::class)
    ->name('api.organizations.risk-summary');

Route::get('organizations/{organizationId}/governance-summary', OrganizationGovernanceSummaryController::class)
    ->name('api.organizations.governance-summary');
