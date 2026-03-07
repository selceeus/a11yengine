<?php

use App\Enums\ScanPageStatus;
use App\Enums\ScanStatus;
use App\Jobs\RunAxeScanPageJob;
use App\Jobs\RunLighthouseScanJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;
use App\Services\ScanPageDispatcher;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['base_url' => 'https://example.com']);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->dispatcher = app(ScanPageDispatcher::class);
});

// ─── Immediate complete (no pages) ───────────────────────────────────────────

it('immediately completes the scan with zero counts when no pages are crawled', function (): void {
    $this->dispatcher->dispatch($this->scan, []);

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Completed)
        ->and($this->scan->fresh()->pages_scanned)->toBe(0)
        ->and($this->scan->fresh()->total_violations)->toBe(0);
});

it('does not create any ScanPage stubs when there are no pages', function (): void {
    $this->dispatcher->dispatch($this->scan, []);

    expect(ScanPage::withoutGlobalScopes()->where('scan_id', $this->scan->id)->count())->toBe(0);
});

// ─── Stub creation ───────────────────────────────────────────────────────────

it('creates one ScanPage stub per page before the batch runs', function (): void {
    Bus::fake();
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
        ['url' => 'https://example.com/about', 'violations' => []],
    ]);

    expect(ScanPage::withoutGlobalScopes()->where('scan_id', $this->scan->id)->count())->toBe(2);
});

it('creates stubs with Pending status and axe_completed=false', function (): void {
    Bus::fake();
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
    ]);

    $stub = ScanPage::withoutGlobalScopes()->where('scan_id', $this->scan->id)->first();

    expect($stub->status)->toBe(ScanPageStatus::Pending)
        ->and($stub->axe_completed)->toBeFalse()
        ->and($stub->lighthouse_completed)->toBeNull();
});

it('sets lighthouse_completed=false on stubs when lighthouse is enabled', function (): void {
    Bus::fake();
    config(['lighthouse.enabled' => true]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
    ]);

    $stub = ScanPage::withoutGlobalScopes()->where('scan_id', $this->scan->id)->first();

    expect($stub->lighthouse_completed)->toBeFalse();
});

// ─── Job dispatching ─────────────────────────────────────────────────────────

it('dispatches a RunAxeScanPageJob for every page when lighthouse is disabled', function (): void {
    Bus::fake();
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
        ['url' => 'https://example.com/about', 'violations' => []],
    ]);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        return $batch->jobs->count() === 2
            && $batch->jobs->every(fn ($job) => $job instanceof RunAxeScanPageJob);
    });
});

it('dispatches RunAxeScanPageJob and RunLighthouseScanJob per page when lighthouse is enabled', function (): void {
    Bus::fake();
    config(['lighthouse.enabled' => true]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
        ['url' => 'https://example.com/about', 'violations' => []],
    ]);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $axeJobs = $batch->jobs->filter(fn ($job) => $job instanceof RunAxeScanPageJob);
        $lighthouseJobs = $batch->jobs->filter(fn ($job) => $job instanceof RunLighthouseScanJob);

        return $axeJobs->count() === 2 && $lighthouseJobs->count() === 2;
    });
});

it('names the batch after the scan id', function (): void {
    Bus::fake();
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
    ]);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === "scan:{$this->scan->id}");
});

// ─── Scan completion via batch then() ────────────────────────────────────────

it('transitions the scan to Completed after the batch finishes', function (): void {
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
    ]);

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Completed);
});

it('counts only Scanned pages toward pages_scanned', function (): void {
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
        ['url' => 'https://example.com/about', 'violations' => []],
    ]);

    expect($this->scan->fresh()->pages_scanned)->toBe(2);
});

it('sums violations_count across all Scanned pages', function (): void {
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => [
            ['id' => 'image-alt', 'impact' => 'critical', 'nodes' => [
                ['target' => ['#logo'], 'failureSummary' => 'Fix.'],
                ['target' => ['#banner'], 'failureSummary' => 'Fix.'],
            ]],
        ]],
        ['url' => 'https://example.com/about', 'violations' => [
            ['id' => 'color-contrast', 'impact' => 'serious', 'nodes' => [
                ['target' => ['#heading'], 'failureSummary' => 'Fix.'],
            ]],
        ]],
    ]);

    expect($this->scan->fresh()->total_violations)->toBe(3);
});

// ─── Metrics calculation after batch ─────────────────────────────────────────

it('stores scan-level metrics after the batch completes', function (): void {
    config(['lighthouse.enabled' => false]);

    $this->dispatcher->dispatch($this->scan, [
        ['url' => 'https://example.com/', 'violations' => []],
    ]);

    expect(\App\Models\ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->whereNull('page_id')
        ->count()
    )->toBeGreaterThan(0);
});
