<?php

use App\Domain\Scans\Scan as ScanDomain;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->domain = new ScanDomain;
    $this->scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
});

it('transitions a scan to running and records started_at', function (): void {
    $result = $this->domain->start($this->scan);

    expect($result->status)->toBe(ScanStatus::Running)
        ->and($result->started_at)->not->toBeNull();
});

it('can start a scan by id', function (): void {
    $result = $this->domain->start($this->scan->id);

    expect($result->status)->toBe(ScanStatus::Running);
});

it('completes a scan and records metadata', function (): void {
    $result = $this->domain->complete($this->scan, pagesScanned: 42, totalViolations: 7, rawOutputPath: 'scans/output.json');

    expect($result->status)->toBe(ScanStatus::Completed)
        ->and($result->pages_scanned)->toBe(42)
        ->and($result->total_violations)->toBe(7)
        ->and($result->raw_output_path)->toBe('scans/output.json')
        ->and($result->completed_at)->not->toBeNull();
});

it('completes a scan without a raw output path', function (): void {
    $result = $this->domain->complete($this->scan, pagesScanned: 10, totalViolations: 0);

    expect($result->status)->toBe(ScanStatus::Completed)
        ->and($result->pages_scanned)->toBe(10)
        ->and($result->total_violations)->toBe(0)
        ->and($result->raw_output_path)->toBeNull();
});

it('can complete a scan by id', function (): void {
    $result = $this->domain->complete($this->scan->id, pagesScanned: 5, totalViolations: 2);

    expect($result->status)->toBe(ScanStatus::Completed)
        ->and($result->pages_scanned)->toBe(5);
});

it('transitions a scan to failed and records completed_at', function (): void {
    $result = $this->domain->fail($this->scan);

    expect($result->status)->toBe(ScanStatus::Failed)
        ->and($result->completed_at)->not->toBeNull();
});

it('can fail a scan by id', function (): void {
    $result = $this->domain->fail($this->scan->id);

    expect($result->status)->toBe(ScanStatus::Failed);
});

it('persists changes to the database', function (): void {
    $this->domain->complete($this->scan, pagesScanned: 100, totalViolations: 15, rawOutputPath: 'scans/abc.json');

    $fresh = Scan::withoutGlobalScopes()->find($this->scan->id);

    expect($fresh->pages_scanned)->toBe(100)
        ->and($fresh->total_violations)->toBe(15)
        ->and($fresh->raw_output_path)->toBe('scans/abc.json')
        ->and($fresh->status)->toBe(ScanStatus::Completed);
});
