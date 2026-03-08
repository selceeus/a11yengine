<?php

use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\LighthouseResult;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanMetric;
use App\Models\User;
use App\Services\CalculateScanMetrics;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->service = app(CalculateScanMetrics::class);
});

// ─── Accessibility risk score ─────────────────────────────────────────────────

it('produces a risk score of 100 when there are no findings', function (): void {
    $this->service->handle($this->scan);

    $metric = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'accessibility_risk_score')
        ->first();

    expect($metric)->not->toBeNull()
        ->and($metric->metric_value)->toBe(100.0);
});

it('applies the correct weights to each severity level', function (): void {
    // 1 critical (5) + 1 serious (3) + 1 moderate (1) + 1 minor (0.5) = 9.5
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::CRITICAL,
    ]);
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::SERIOUS,
    ]);
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::MODERATE,
    ]);
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::MINOR,
    ]);

    $this->service->handle($this->scan);

    $score = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'accessibility_risk_score')
        ->value('metric_value');

    expect($score)->toBe(90.5);
});

it('clamps the risk score to 0 when weighted sum exceeds 100', function (): void {
    Finding::factory()->for($this->agency)->for($this->scan)->count(25)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::CRITICAL,
    ]);

    $this->service->handle($this->scan);

    $score = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'accessibility_risk_score')
        ->value('metric_value');

    expect($score)->toBe(0.0);
});

it('ignores INFO severity findings in the score calculation', function (): void {
    Finding::factory()->for($this->agency)->for($this->scan)->count(5)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::INFO,
    ]);

    $this->service->handle($this->scan);

    $score = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'accessibility_risk_score')
        ->value('metric_value');

    expect($score)->toBe(100.0);
});

// ─── Issue counts ─────────────────────────────────────────────────────────────

it('counts distinct issues linked to findings for total_issue_count', function (): void {
    $issue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    Finding::factory()->for($this->agency)->for($this->scan)->count(3)->create([
        'property_id' => $this->property->id,
        'issue_id' => $issue->id,
        'severity' => FindingSeverity::MODERATE,
    ]);

    $this->service->handle($this->scan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'total_issue_count')
        ->value('metric_value');

    expect($count)->toBe(1.0);
});

it('does not count findings without an issue for total_issue_count', function (): void {
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'issue_id' => null,
        'severity' => FindingSeverity::MODERATE,
    ]);

    $this->service->handle($this->scan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'total_issue_count')
        ->value('metric_value');

    expect($count)->toBe(0.0);
});

it('counts only critical-severity issues for critical_issue_count', function (): void {
    $criticalIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'severity' => IssueSeverity::Critical,
    ]);
    $highIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'severity' => IssueSeverity::High,
    ]);

    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'issue_id' => $criticalIssue->id,
        'severity' => FindingSeverity::CRITICAL,
    ]);
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'issue_id' => $highIssue->id,
        'severity' => FindingSeverity::SERIOUS,
    ]);

    $this->service->handle($this->scan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'critical_issue_count')
        ->value('metric_value');

    expect($count)->toBe(1.0);
});

// ─── Lighthouse metrics ───────────────────────────────────────────────────────

it('records lighthouse_accessibility_avg when lighthouse results exist', function (): void {
    LighthouseResult::factory()->for($this->agency)->for($this->scan)->create([
        'accessibility_score' => 80,
        'performance_score' => 70,
    ]);
    LighthouseResult::factory()->for($this->agency)->for($this->scan)->create([
        'accessibility_score' => 60,
        'performance_score' => 90,
    ]);

    $this->service->handle($this->scan);

    $accessibility = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'lighthouse_accessibility_avg')
        ->value('metric_value');

    $performance = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'lighthouse_performance_avg')
        ->value('metric_value');

    expect($accessibility)->toBe(70.0)
        ->and($performance)->toBe(80.0);
});

it('does not record lighthouse metrics when no lighthouse results exist', function (): void {
    $this->service->handle($this->scan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->whereIn('metric_name', ['lighthouse_accessibility_avg', 'lighthouse_performance_avg'])
        ->count();

    expect($count)->toBe(0);
});

// ─── Risk trend ───────────────────────────────────────────────────────────────

it('does not record risk_trend when no prior scan exists for the property', function (): void {
    $this->service->handle($this->scan);

    $metric = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'risk_trend')
        ->first();

    expect($metric)->toBeNull();
});

it('records a positive risk_trend when score improved compared to prior scan', function (): void {
    // $this->scan (lower ID) acts as the prior scan; $currentScan (higher ID) is the new one.
    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 80.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $currentScan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    // Current scan has no findings → score = 100 → trend = 100 - 80 = +20
    $this->service->handle($currentScan);

    $trend = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'risk_trend')
        ->value('metric_value');

    expect($trend)->toBe(20.0);
});

it('records a negative risk_trend when score worsened compared to prior scan', function (): void {
    // $this->scan (lower ID) acts as the prior scan; $currentScan (higher ID) is the new one.
    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 95.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $currentScan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    // 2 critical findings → score = 100 - 10 = 90 → trend = 90 - 95 = -5
    Finding::factory()->for($this->agency)->for($currentScan)->count(2)->create([
        'property_id' => $this->property->id,
        'severity' => FindingSeverity::CRITICAL,
    ]);

    $this->service->handle($currentScan);

    $trend = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'risk_trend')
        ->value('metric_value');

    expect($trend)->toBe(-5.0);
});

it('does not use prior scans from a different property for risk_trend', function (): void {
    $otherProperty = Property::factory()->for($this->agency)->for($this->organization)->create();
    $otherScan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($otherProperty)
        ->create();

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $otherScan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 70.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $this->service->handle($this->scan);

    $trend = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->where('metric_name', 'risk_trend')
        ->first();

    expect($trend)->toBeNull();
});

// ─── All metrics written to the database ─────────────────────────────────────

it('writes all expected scan-level metrics when prior scan has no lighthouse results', function (): void {
    // $this->scan (lower ID) acts as the prior scan; $currentScan (higher ID) is the new one.
    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 90.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $currentScan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    LighthouseResult::factory()->for($this->agency)->for($currentScan)->create([
        'accessibility_score' => 85,
        'performance_score' => 75,
    ]);

    $this->service->handle($currentScan);

    $names = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->pluck('metric_name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe([
        'accessibility_risk_score',
        'critical_issue_count',
        'lighthouse_accessibility_avg',
        'lighthouse_performance_avg',
        'new_issue_count',
        'resolved_issue_count',
        'risk_trend',
        'total_issue_count',
    ]);
});

it('stores all metrics with a null page_id', function (): void {
    $this->service->handle($this->scan);

    $withPage = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->whereNotNull('page_id')
        ->count();

    expect($withPage)->toBe(0);
});

// ─── Issue delta metrics ──────────────────────────────────────────────────────

it('does not record resolved_issue_count or new_issue_count when no prior scan exists', function (): void {
    $this->service->handle($this->scan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->whereIn('metric_name', ['resolved_issue_count', 'new_issue_count'])
        ->count();

    expect($count)->toBe(0);
});

it('records the correct resolved_issue_count', function (): void {
    $issue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    // Prior scan has the issue
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'issue_id' => $issue->id,
    ]);

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 90.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    // Current scan — issue is gone (resolved)
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->service->handle($currentScan);

    $resolved = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'resolved_issue_count')
        ->value('metric_value');

    expect($resolved)->toBe(1.0);
});

it('records the correct new_issue_count', function (): void {
    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 90.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    // Current scan has a new issue not in the prior scan
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
    $newIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    Finding::factory()->for($this->agency)->for($currentScan)->create([
        'property_id' => $this->property->id,
        'issue_id' => $newIssue->id,
    ]);

    $this->service->handle($currentScan);

    $new = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'new_issue_count')
        ->value('metric_value');

    expect($new)->toBe(1.0);
});

it('excludes findings without an issue_id from delta counts', function (): void {
    // Prior scan: one finding without issue_id
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'issue_id' => null,
    ]);

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 90.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    // Current scan: same finding pattern
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
    Finding::factory()->for($this->agency)->for($currentScan)->create([
        'property_id' => $this->property->id,
        'issue_id' => null,
    ]);

    $this->service->handle($currentScan);

    $resolved = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'resolved_issue_count')
        ->value('metric_value');

    $new = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'new_issue_count')
        ->value('metric_value');

    expect($resolved)->toBe(0.0)
        ->and($new)->toBe(0.0);
});

it('records both resolved and new issue counts when issues changed between scans', function (): void {
    $oldIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
    $newIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    // Prior scan: old issue only
    Finding::factory()->for($this->agency)->for($this->scan)->create([
        'property_id' => $this->property->id,
        'issue_id' => $oldIssue->id,
    ]);

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 90.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    // Current scan: new issue only
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
    Finding::factory()->for($this->agency)->for($currentScan)->create([
        'property_id' => $this->property->id,
        'issue_id' => $newIssue->id,
    ]);

    $this->service->handle($currentScan);

    $resolved = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'resolved_issue_count')
        ->value('metric_value');

    $new = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'new_issue_count')
        ->value('metric_value');

    expect($resolved)->toBe(1.0)
        ->and($new)->toBe(1.0);
});

it('does not use prior scans from a different property for issue delta counts', function (): void {
    $otherProperty = Property::factory()->for($this->agency)->for($this->organization)->create();
    $otherScan = Scan::factory()->for($this->agency)->for($this->organization)->for($otherProperty)->create();

    $issue = Issue::factory()->for($this->agency)->for($this->organization)->for($otherProperty)->create();

    Finding::factory()->for($this->agency)->for($otherScan)->create([
        'property_id' => $otherProperty->id,
        'issue_id' => $issue->id,
    ]);

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $otherScan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 80.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    // $this->scan belongs to $this->property — no prior scan for it
    $this->service->handle($this->scan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $this->scan->id)
        ->whereIn('metric_name', ['resolved_issue_count', 'new_issue_count'])
        ->count();

    expect($count)->toBe(0);
});

// ─── Lighthouse delta metrics ─────────────────────────────────────────────────

it('does not record lighthouse deltas when no prior lighthouse metrics exist', function (): void {
    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 80.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    LighthouseResult::factory()->for($this->agency)->for($currentScan)->create([
        'accessibility_score' => 85,
        'performance_score' => 75,
    ]);

    $this->service->handle($currentScan);

    $count = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->whereIn('metric_name', ['lighthouse_accessibility_delta', 'lighthouse_performance_delta'])
        ->count();

    expect($count)->toBe(0);
});

it('records a positive lighthouse_accessibility_delta when accessibility improved', function (): void {
    ScanMetric::withoutGlobalScopes()->insert([
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'accessibility_risk_score', 'metric_value' => 80.0, 'metric_source' => 'axe', 'created_at' => now()],
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'lighthouse_accessibility_avg', 'metric_value' => 70.0, 'metric_source' => 'lighthouse', 'created_at' => now()],
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'lighthouse_performance_avg', 'metric_value' => 60.0, 'metric_source' => 'lighthouse', 'created_at' => now()],
    ]);

    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    // Accessibility improved: 70 → 90 = +20
    LighthouseResult::factory()->for($this->agency)->for($currentScan)->create([
        'accessibility_score' => 90,
        'performance_score' => 60,
    ]);

    $this->service->handle($currentScan);

    $delta = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'lighthouse_accessibility_delta')
        ->value('metric_value');

    expect($delta)->toBe(20.0);
});

it('records a negative lighthouse_performance_delta when performance worsened', function (): void {
    ScanMetric::withoutGlobalScopes()->insert([
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'accessibility_risk_score', 'metric_value' => 80.0, 'metric_source' => 'axe', 'created_at' => now()],
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'lighthouse_accessibility_avg', 'metric_value' => 80.0, 'metric_source' => 'lighthouse', 'created_at' => now()],
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'lighthouse_performance_avg', 'metric_value' => 80.0, 'metric_source' => 'lighthouse', 'created_at' => now()],
    ]);

    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    // Performance worsened: 80 → 50 = -30
    LighthouseResult::factory()->for($this->agency)->for($currentScan)->create([
        'accessibility_score' => 80,
        'performance_score' => 50,
    ]);

    $this->service->handle($currentScan);

    $delta = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->where('metric_name', 'lighthouse_performance_delta')
        ->value('metric_value');

    expect($delta)->toBe(-30.0);
});

it('writes all 10 metrics when prior scan also has lighthouse results', function (): void {
    ScanMetric::withoutGlobalScopes()->insert([
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'accessibility_risk_score', 'metric_value' => 90.0, 'metric_source' => 'axe', 'created_at' => now()],
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'lighthouse_accessibility_avg', 'metric_value' => 75.0, 'metric_source' => 'lighthouse', 'created_at' => now()],
        ['agency_id' => $this->agency->id, 'scan_id' => $this->scan->id, 'page_id' => null, 'metric_name' => 'lighthouse_performance_avg', 'metric_value' => 65.0, 'metric_source' => 'lighthouse', 'created_at' => now()],
    ]);

    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    LighthouseResult::factory()->for($this->agency)->for($currentScan)->create([
        'accessibility_score' => 85,
        'performance_score' => 75,
    ]);

    $this->service->handle($currentScan);

    $names = ScanMetric::withoutGlobalScopes()
        ->where('scan_id', $currentScan->id)
        ->pluck('metric_name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe([
        'accessibility_risk_score',
        'critical_issue_count',
        'lighthouse_accessibility_avg',
        'lighthouse_accessibility_delta',
        'lighthouse_performance_avg',
        'lighthouse_performance_delta',
        'new_issue_count',
        'resolved_issue_count',
        'risk_trend',
        'total_issue_count',
    ]);
});
