<?php

use App\Domain\Scans\RecordScanMetrics;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanMetric;
use App\Models\ScanPage;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
    $this->page = ScanPage::factory()->for($this->agency)->for($this->scan)->create();

    $this->service = new RecordScanMetrics;
});

// ─── Basic insertion ──────────────────────────────────────────────────────────

it('inserts one row per metric name', function (): void {
    $this->service->record($this->scan, $this->page, [
        'accessibility_issue_count' => 14,
        'lighthouse_performance' => 82,
    ], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->count())->toBe(2);
});

it('inserts a single metric correctly', function (): void {
    $this->service->record($this->scan, $this->page, [
        'accessibility_issue_count' => 14,
    ], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->first();

    expect($metric->metric_name)->toBe('accessibility_issue_count')
        ->and($metric->metric_value)->toBe(14.0)
        ->and($metric->metric_source)->toBe('axe');
});

it('links the metric to the correct scan and page', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 5], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->first();

    expect($metric->scan_id)->toBe($this->scan->id)
        ->and($metric->page_id)->toBe($this->page->id);
});

it('links the metric to the correct agency', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 5], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->first();

    expect($metric->agency_id)->toBe($this->agency->id);
});

// ─── Decimal precision ────────────────────────────────────────────────────────

it('stores float values with decimal precision', function (): void {
    $this->service->record($this->scan, $this->page, [
        'first_contentful_paint' => 1234.5,
    ], 'lighthouse');

    expect(ScanMetric::withoutGlobalScopes()->first()->metric_value)->toBe(1234.5);
});

it('stores small float values without precision loss', function (): void {
    $this->service->record($this->scan, $this->page, [
        'cumulative_layout_shift' => 0.0125,
    ], 'lighthouse');

    expect(ScanMetric::withoutGlobalScopes()->first()->metric_value)->toBe(0.0125);
});

it('stores integer values as floats via the cast', function (): void {
    $this->service->record($this->scan, $this->page, [
        'lighthouse_performance' => 92,
    ], 'lighthouse');

    expect(ScanMetric::withoutGlobalScopes()->first()->metric_value)->toBe(92.0);
});

// ─── Sources ──────────────────────────────────────────────────────────────────

it('stores the correct source for axe metrics', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 3], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->first()->metric_source)->toBe('axe');
});

it('stores the correct source for lighthouse metrics', function (): void {
    $this->service->record($this->scan, $this->page, ['lighthouse_performance' => 90], 'lighthouse');

    expect(ScanMetric::withoutGlobalScopes()->first()->metric_source)->toBe('lighthouse');
});

// ─── Edge cases ───────────────────────────────────────────────────────────────

it('does nothing when metrics array is empty', function (): void {
    $this->service->record($this->scan, $this->page, [], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->count())->toBe(0);
});

it('sets created_at on insertion', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 1], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->first()->created_at)->not->toBeNull();
});

it('does not set updated_at', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 1], 'axe');

    $raw = \Illuminate\Support\Facades\DB::table('scan_metrics')->first();

    expect(isset($raw->updated_at))->toBeFalse();
});

// ─── Model static helper ──────────────────────────────────────────────────────

it('can record metrics via the ScanMetric::recordBulk static helper', function (): void {
    ScanMetric::recordBulk($this->scan, $this->page, [
        'accessibility_issue_count' => 14,
        'lighthouse_performance' => 82,
    ], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->count())->toBe(2);
});

it('static helper produces identical rows to the service call', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 7], 'axe');
    ScanMetric::recordBulk($this->scan, $this->page, ['accessibility_issue_count' => 7], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->count())->toBe(2);

    $rows = ScanMetric::withoutGlobalScopes()->get();
    expect($rows[0]->metric_name)->toBe($rows[1]->metric_name)
        ->and($rows[0]->metric_value)->toBe($rows[1]->metric_value)
        ->and($rows[0]->metric_source)->toBe($rows[1]->metric_source);
});

// ─── Model relationships ──────────────────────────────────────────────────────

it('can retrieve the scan via the relationship', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 1], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->with('scan')->first();

    expect($metric->scan)->toBeInstanceOf(Scan::class)
        ->and($metric->scan->id)->toBe($this->scan->id);
});

it('can retrieve the page via the relationship', function (): void {
    $this->service->record($this->scan, $this->page, ['accessibility_issue_count' => 1], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->with('page')->first();

    expect($metric->page)->toBeInstanceOf(ScanPage::class)
        ->and($metric->page->id)->toBe($this->page->id);
});

// ─── Null page_id (scan-level metrics) ───────────────────────────────────────

it('accepts a null page and stores page_id as null', function (): void {
    $this->service->record($this->scan, null, ['accessibility_risk_score' => 95.5], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->first();

    expect($metric->page_id)->toBeNull();
});

it('resolves a null page relationship to null', function (): void {
    $this->service->record($this->scan, null, ['accessibility_risk_score' => 95.5], 'axe');

    $metric = ScanMetric::withoutGlobalScopes()->with('page')->first();

    expect($metric->page)->toBeNull();
});

it('inserts multiple scan-level metrics when page is null', function (): void {
    $this->service->record($this->scan, null, [
        'accessibility_risk_score' => 95.5,
        'total_issue_count' => 3,
    ], 'axe');

    expect(ScanMetric::withoutGlobalScopes()->count())->toBe(2);

    ScanMetric::withoutGlobalScopes()->each(function ($m): void {
        expect($m->page_id)->toBeNull();
    });
});
