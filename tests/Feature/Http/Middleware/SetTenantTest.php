<?php

use App\Models\Agency;
use Illuminate\Support\Facades\Route;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    // Route-param variant: slug is part of the URL.
    Route::middleware('tenant')
        ->get('/test-tenant/{tenant}', fn () => response()->json([
            'agency_id' => app(Agency::class)->id,
        ]));

    // Header variant: slug is supplied via X-Tenant header (no route param).
    Route::middleware('tenant')
        ->get('/test-tenant-header', fn () => response()->json([
            'agency_id' => app(Agency::class)->id,
        ]));
});

it('resolves the agency from the route tenant parameter', function (): void {
    $agency = Agency::factory()->create(['slug' => 'acme']);

    $this->getJson("/test-tenant/{$agency->slug}")
        ->assertOk()
        ->assertJson(['agency_id' => $agency->id]);
});

it('resolves the agency from the X-Tenant request header', function (): void {
    $agency = Agency::factory()->create(['slug' => 'acme-header']);

    $this->getJson('/test-tenant-header', ['X-Tenant' => $agency->slug])
        ->assertOk()
        ->assertJson(['agency_id' => $agency->id]);
});

it('returns 404 when the tenant route parameter is missing', function (): void {
    Route::middleware('tenant')->get('/test-no-param', fn () => response()->json([]));

    $this->getJson('/test-no-param')->assertNotFound();
});

it('returns 404 when the slug does not match any agency', function (): void {
    $this->getJson('/test-tenant/unknown-slug')->assertNotFound();
});

it('returns 404 when neither a route param nor a header is present', function (): void {
    $this->getJson('/test-tenant-header')->assertNotFound();
});

it('binds the agency under the Agency::class key', function (): void {
    $agency = Agency::factory()->create(['slug' => 'dual-bind']);

    $this->getJson("/test-tenant/{$agency->slug}")->assertOk();

    expect(app(Agency::class)->id)->toBe($agency->id);
});

it('passes through to the next middleware when the tenant is resolved', function (): void {
    $agency = Agency::factory()->create(['slug' => 'pass-through']);

    $this->getJson("/test-tenant/{$agency->slug}")->assertOk();
});
