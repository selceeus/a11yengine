<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SendInvitationController;
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
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::patch('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

    Route::get('scans', [ScanController::class, 'index'])->name('scans.index');
    Route::post('scans', [ScanController::class, 'store'])->name('scans.store');
    Route::get('scans/{scan}', [ScanController::class, 'show'])->name('scans.show');

    Route::post('invitations', SendInvitationController::class)->name('invitations.send');
});

Route::middleware('guest')->group(function (): void {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
