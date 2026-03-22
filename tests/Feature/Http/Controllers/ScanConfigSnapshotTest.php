<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── default config ───────────────────────────────────────────────────────────

it('snapshots the default scan config onto the scan when none is provided', function (): void {
    $this->post(route('scans.store'), ['property_id' => $this->property->id]);

    $scan = Scan::query()->first();

    expect($scan->scan_config)->toBe([
        'max_pages' => 50,
        'include_patterns' => [],
        'exclude_patterns' => [],
        'wcag_version' => 'wcag21',
    ]);
});

// ─── request override ────────────────────────────────────────────────────────

it('uses the request scan_config when provided', function (): void {
    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'scan_config' => [
            'max_pages' => 25,
            'wcag_version' => 'wcag22',
        ],
    ]);

    $scan = Scan::query()->first();

    expect($scan->scan_config['max_pages'])->toBe(25)
        ->and($scan->scan_config['wcag_version'])->toBe('wcag22');
});

// ─── property default inheritance ────────────────────────────────────────────

it('inherits the property scan_config defaults when no request override is given', function (): void {
    $this->property->update(['scan_config' => [
        'max_pages' => 100,
        'include_patterns' => ['/blog'],
        'exclude_patterns' => [],
        'wcag_version' => 'wcag21',
    ]]);

    $this->post(route('scans.store'), ['property_id' => $this->property->id]);

    $scan = Scan::query()->first();

    expect($scan->scan_config['max_pages'])->toBe(100)
        ->and($scan->scan_config['include_patterns'])->toBe(['/blog']);
});

it('merges request override on top of property defaults', function (): void {
    $this->property->update(['scan_config' => [
        'max_pages' => 100,
        'include_patterns' => ['/docs'],
        'exclude_patterns' => [],
        'wcag_version' => 'wcag21',
    ]]);

    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'scan_config' => ['max_pages' => 10],
    ]);

    $scan = Scan::query()->first();

    // Request value wins for max_pages
    expect($scan->scan_config['max_pages'])->toBe(10)
        // Property default retained for include_patterns (not overridden)
        ->and($scan->scan_config['include_patterns'])->toBe(['/docs']);
});

// ─── validation ───────────────────────────────────────────────────────────────

it('rejects an invalid wcag_version value', function (): void {
    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'scan_config' => ['wcag_version' => 'wcag30'],
    ])->assertSessionHasErrors('scan_config.wcag_version');

    Queue::assertNothingPushed();
});

it('rejects a max_pages value of zero', function (): void {
    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'scan_config' => ['max_pages' => 0],
    ])->assertSessionHasErrors('scan_config.max_pages');

    Queue::assertNothingPushed();
});

it('rejects a max_pages value exceeding 500', function (): void {
    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'scan_config' => ['max_pages' => 501],
    ])->assertSessionHasErrors('scan_config.max_pages');

    Queue::assertNothingPushed();
});

it('accepts wcag21 and wcag22 as valid wcag_version values', function (): void {
    foreach (['wcag21', 'wcag22'] as $version) {
        $this->post(route('scans.store'), [
            'property_id' => $this->property->id,
            'scan_config' => ['wcag_version' => $version],
        ])->assertSessionHasNoErrors();
    }
});
