<?php

use App\Http\Controllers\Settings\ActivityLogExportController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\NotificationEmailRouteController;
use App\Http\Controllers\Settings\NotificationPreferencesController;
use App\Http\Controllers\Settings\NotificationWebhookRouteController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ScheduledScansController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/scheduled-scans', [ScheduledScansController::class, 'index'])
        ->name('scheduled-scans.index');

    Route::get('settings/scheduled-scans/{scheduledScan}', [ScheduledScansController::class, 'show'])
        ->name('scheduled-scans.show');

    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])
        ->name('notification-preferences.edit');

    Route::patch('settings/notifications', [NotificationPreferencesController::class, 'update'])
        ->name('notification-preferences.update');

    Route::get('settings/api-keys', [ApiKeyController::class, 'index'])
        ->name('api-keys.index');

    Route::post('settings/api-keys', [ApiKeyController::class, 'store'])
        ->name('api-keys.store');

    Route::delete('settings/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])
        ->name('api-keys.destroy');

    Route::get('settings/integrations', [IntegrationController::class, 'index'])
        ->name('integrations.index');

    Route::post('settings/integrations', [IntegrationController::class, 'store'])
        ->name('integrations.store');

    Route::get('settings/integrations/{integration}', [IntegrationController::class, 'show'])
        ->name('integrations.show');

    Route::delete('settings/integrations/{integration}', [IntegrationController::class, 'destroy'])
        ->name('integrations.destroy');

    Route::post('settings/integrations/{integration}/test', [IntegrationController::class, 'test'])
        ->name('integrations.test');

    Route::get('settings/notification-email-routes', [NotificationEmailRouteController::class, 'index'])
        ->name('notification-email-routes.index');

    Route::post('settings/notification-email-routes', [NotificationEmailRouteController::class, 'store'])
        ->name('notification-email-routes.store');

    Route::delete('settings/notification-email-routes/{notificationEmailRoute}', [NotificationEmailRouteController::class, 'destroy'])
        ->name('notification-email-routes.destroy');

    Route::get('settings/notification-webhook-routes', [NotificationWebhookRouteController::class, 'index'])
        ->name('notification-webhook-routes.index');

    Route::post('settings/notification-webhook-routes', [NotificationWebhookRouteController::class, 'store'])
        ->name('notification-webhook-routes.store');

    Route::delete('settings/notification-webhook-routes/{notificationWebhookRoute}', [NotificationWebhookRouteController::class, 'destroy'])
        ->name('notification-webhook-routes.destroy');

    Route::get('settings/activity-log/export', ActivityLogExportController::class)
        ->name('activity-log.export');
});
