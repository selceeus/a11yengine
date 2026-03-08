<?php

use App\Enums\FindingSeverity;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanMetric;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->org = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);
});

// ─── Auth & authorization ─────────────────────────────────────────────────────

it('requires authentication', function (): void {
    $this->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $other = Agency::factory()->create();
    $otherUser = User::factory()->create(['agency_id' => $other->id]);

    $this->actingAs($otherUser)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertForbidden();
});

it('returns 404 for a non-existent site', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', 99999))
        ->assertNotFound();
});

// ─── Response structure ───────────────────────────────────────────────────────

it('returns the correct top-level response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJsonStructure(['riskScore', 'severityBreakdown', 'lighthouse', 'riskTrend']);
});

// ─── Risk score ───────────────────────────────────────────────────────────────

it('returns null riskScore when no completed scans exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['riskScore' => null]);
});

it('returns the accessibility_risk_score from the latest completed scan', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 73.5,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['riskScore' => 73.5]);
});

it('uses the most recent completed scan for riskScore, not an older one', function (): void {
    $oldScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now()->subDays(7),
    ]);
    $newScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now(),
    ]);

    foreach ([[$oldScan->id, 50.0], [$newScan->id, 90.0]] as [$scanId, $value]) {
        ScanMetric::withoutGlobalScopes()->insert([
            'agency_id' => $this->agency->id,
            'scan_id' => $scanId,
            'page_id' => null,
            'metric_name' => 'accessibility_risk_score',
            'metric_value' => $value,
            'metric_source' => 'axe',
            'created_at' => now(),
        ]);
    }

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['riskScore' => 90.0]);
});

it('ignores pending scans when resolving the latest scan', function (): void {
    $completedScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now()->subDays(3),
    ]);

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $completedScan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 65.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    // A newer but pending scan should not take precedence
    Scan::factory()->for($this->agency)->for($this->org)->for($this->property)->create([
        'status' => ScanStatus::Pending,
        'completed_at' => null,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['riskScore' => 65.0]);
});

// ─── Severity breakdown ───────────────────────────────────────────────────────

it('returns zero severityBreakdown when no scans exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['severityBreakdown' => ['critical' => 0, 'serious' => 0, 'moderate' => 0]]);
});

it('returns correct finding counts by severity for the latest scan', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(3)->create([
        'severity' => FindingSeverity::CRITICAL,
    ]);
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(5)->create([
        'severity' => FindingSeverity::SERIOUS,
    ]);
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(2)->create([
        'severity' => FindingSeverity::MODERATE,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['severityBreakdown' => ['critical' => 3, 'serious' => 5, 'moderate' => 2]]);
});

it('does not include findings from a different scan in the breakdown', function (): void {
    $latestScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now(),
    ]);
    $olderScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now()->subDays(7),
    ]);

    Finding::factory()->for($this->agency)->for($this->property)->for($olderScan)->count(10)->create([
        'severity' => FindingSeverity::CRITICAL,
    ]);
    Finding::factory()->for($this->agency)->for($this->property)->for($latestScan)->count(1)->create([
        'severity' => FindingSeverity::CRITICAL,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['severityBreakdown' => ['critical' => 1]]);
});

// ─── Lighthouse ───────────────────────────────────────────────────────────────

it('returns null lighthouse values when no completed scans exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['lighthouse' => ['accessibility' => null, 'performance' => null, 'bestPractices' => null]]);
});

it('returns null lighthouse values when no lighthouse results exist for the scan', function (): void {
    Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['lighthouse' => ['accessibility' => null, 'performance' => null, 'bestPractices' => null]]);
});

it('returns averaged lighthouse scores rounded to integers', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    LighthouseResult::factory()->for($this->agency)->for($scan)->create([
        'accessibility_score' => 80,
        'performance_score' => 60,
        'best_practices_score' => 90,
    ]);
    LighthouseResult::factory()->for($this->agency)->for($scan)->create([
        'accessibility_score' => 100,
        'performance_score' => 80,
        'best_practices_score' => 70,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['lighthouse' => ['accessibility' => 90, 'performance' => 70, 'bestPractices' => 80]]);
});

// ─── Risk trend ───────────────────────────────────────────────────────────────

it('returns an empty riskTrend array when no metrics exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['riskTrend' => []]);
});

it('returns riskTrend entries with date and score keys', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now(),
    ]);

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 64.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk();

    $entry = $response->json('riskTrend.0');

    expect($entry)->toHaveKeys(['date', 'score'])
        ->and($entry['score'])->toBe(64);
});

it('returns riskTrend entries in chronological order', function (): void {
    $scan1 = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now()->subDays(14),
    ]);
    $scan2 = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now()->subDays(7),
    ]);
    $scan3 = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now(),
    ]);

    foreach ([[$scan1->id, 72.0], [$scan2->id, 68.0], [$scan3->id, 64.0]] as [$scanId, $value]) {
        ScanMetric::withoutGlobalScopes()->insert([
            'agency_id' => $this->agency->id,
            'scan_id' => $scanId,
            'page_id' => null,
            'metric_name' => 'accessibility_risk_score',
            'metric_value' => $value,
            'metric_source' => 'axe',
            'created_at' => now(),
        ]);
    }

    $trend = $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->json('riskTrend');

    expect($trend)->toHaveCount(3)
        ->and($trend[0]['score'])->toBe(72)
        ->and($trend[1]['score'])->toBe(68)
        ->and($trend[2]['score'])->toBe(64);
});

it('does not include riskTrend entries from a different property', function (): void {
    $otherProperty = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);
    $otherScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($otherProperty)->create();

    ScanMetric::withoutGlobalScopes()->insert([
        'agency_id' => $this->agency->id,
        'scan_id' => $otherScan->id,
        'page_id' => null,
        'metric_name' => 'accessibility_risk_score',
        'metric_value' => 80.0,
        'metric_source' => 'axe',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-dashboard', $this->property->id))
        ->assertOk()
        ->assertJson(['riskTrend' => []]);
});
