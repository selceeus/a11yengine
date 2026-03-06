<?php

use App\Models\Agency;
use App\Models\Finding;
use App\Models\Property;
use App\Models\Scan;
use App\Models\Scopes\TenantScope;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('automatically computes fingerprint on create', function (): void {
    $finding = Finding::factory()->create();

    $expected = sha1($finding->rule_key.'|'.($finding->element_identifier ?? '').'|'.$finding->page_url);

    expect($finding->fingerprint)->toBe($expected);
});

it('does not overwrite an explicitly set fingerprint', function (): void {
    $agency = Agency::factory()->create();
    $scan = Scan::factory()->for($agency)->create();
    $property = Property::factory()->for($agency)->for($scan->organization)->create();

    $custom = sha1('custom');

    $finding = Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => 'img.logo',
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
        'fingerprint' => $custom,
    ]);

    expect($finding->fingerprint)->toBe($custom);
});

it('recomputes fingerprint when rule_key changes', function (): void {
    $finding = Finding::factory()->create([
        'rule_key' => 'wcag-1.1.1',
        'element_identifier' => 'img',
        'page_url' => 'https://example.com',
    ]);

    $finding->fingerprint = null;
    $finding->rule_key = 'wcag-2.1.1';
    $finding->save();

    $expected = sha1('wcag-2.1.1|img|https://example.com');

    expect($finding->fingerprint)->toBe($expected);
});

it('handles null element_identifier in fingerprint', function (): void {
    $agency = Agency::factory()->create();
    $scan = Scan::factory()->for($agency)->create();
    $property = Property::factory()->for($agency)->for($scan->organization)->create();

    $finding = Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => null,
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ]);

    $expected = sha1('wcag-1.1.1||https://example.com');

    expect($finding->fingerprint)->toBe($expected);
});

it('enforces uniqueness of fingerprint per scan', function (): void {
    $agency = Agency::factory()->create();
    $scan = Scan::factory()->for($agency)->create();
    $property = Property::factory()->for($agency)->for($scan->organization)->create();

    $attrs = [
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => 'img',
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ];

    Finding::withoutGlobalScope(TenantScope::class)->create($attrs);

    expect(fn () => Finding::withoutGlobalScope(TenantScope::class)->create($attrs))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows the same fingerprint across different scans', function (): void {
    $agency = Agency::factory()->create();
    $scanA = Scan::factory()->for($agency)->create();
    $scanB = Scan::factory()->for($agency)->create();
    $property = Property::factory()->for($agency)->for($scanA->organization)->create();

    $base = [
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => 'img',
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ];

    $findingA = Finding::withoutGlobalScope(TenantScope::class)->create(array_merge($base, ['scan_id' => $scanA->id]));
    $findingB = Finding::withoutGlobalScope(TenantScope::class)->create(array_merge($base, ['scan_id' => $scanB->id]));

    expect($findingA->fingerprint)->toBe($findingB->fingerprint);
});
