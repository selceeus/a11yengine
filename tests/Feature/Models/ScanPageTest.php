<?php

use App\Enums\ScanPageStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\Scopes\TenantScope;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('belongs to a scan', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $page = ScanPage::factory()->for($agency)->for($scan)->create();

    expect($page->scan)->toBeInstanceOf(Scan::class)
        ->and($page->scan->is($scan))->toBeTrue();
});

it('scan has many scan pages', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    ScanPage::factory()->count(3)->for($agency)->for($scan)->create();

    expect($scan->scanPages)->toHaveCount(3)
        ->each->toBeInstanceOf(ScanPage::class);
});

it('casts status to ScanPageStatus enum', function (): void {
    $agency = Agency::factory()->create();
    $scan = Scan::factory()->for($agency)->create();

    $page = ScanPage::factory()->for($agency)->for($scan)->scanned()->create();

    expect($page->status)->toBe(ScanPageStatus::Scanned);
});

it('casts violations_count to integer', function (): void {
    $agency = Agency::factory()->create();
    $scan = Scan::factory()->for($agency)->create();

    $page = ScanPage::factory()->for($agency)->for($scan)->create(['violations_count' => 7]);

    expect($page->violations_count)->toBe(7);
});

it('applies tenant scope to only return scan pages for authenticated user agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $scanA = Scan::factory()->for($agencyA)->create();
    $scanB = Scan::factory()->for($agencyB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);
    test()->actingAs($user);

    ScanPage::factory()->count(2)->for($agencyA)->for($scanA)->create();
    ScanPage::factory()->for($agencyB)->for($scanB)->create();

    expect(ScanPage::query()->count())->toBe(2)
        ->and(ScanPage::withoutGlobalScope(TenantScope::class)->count())->toBe(3);
});

it('tenant scope does not return scan pages from another agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $scanB = Scan::factory()->for($agencyB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);
    test()->actingAs($user);

    ScanPage::factory()->for($agencyB)->for($scanB)->create();

    expect(ScanPage::query()->count())->toBe(0);
});
