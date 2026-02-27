<?php

use App\Http\Controllers\Api\OrganizationRiskController;
use Illuminate\Support\Facades\Route;

Route::post('organizations/{organizationId}/risk-snapshot', OrganizationRiskController::class)
    ->name('api.organizations.risk-snapshot');
