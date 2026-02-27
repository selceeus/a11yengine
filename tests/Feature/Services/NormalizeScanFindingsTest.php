<?php

use App\Domain\Issues\NormalizeScanFindings;
use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();
    $this->scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = new NormalizeScanFindings;
});

it('creates a new issue when no matching open issue exists', function (): void {
    $finding = Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'severity' => FindingSeverity::CRITICAL,
        'page_url' => 'https://example.com/page',
        'detected_at' => now(),
    ]);

    $this->service->handle($this->scan);

    expect(Issue::query()->count())->toBe(1);

    $issue = Issue::query()->first();

    expect($issue->rule_key)->toBe('wcag-1.1.1')
        ->and($issue->status)->toBe(IssueStatus::Open)
        ->and($issue->severity)->toBe(IssueSeverity::Critical)
        ->and($issue->occurrence_count)->toBe(1)
        ->and($issue->risk_weight)->toBe(100)
        ->and($issue->property_id)->toBe($this->property->id)
        ->and($issue->agency_id)->toBe($this->agency->id);
});

it('assigns issue_id to finding after creating new issue', function (): void {
    $finding = Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com/page',
        'detected_at' => now(),
    ]);

    $this->service->handle($this->scan);

    expect($finding->fresh()->issue_id)->not->toBeNull()
        ->and($finding->fresh()->issue_id)->toBe(Issue::query()->first()->id);
});

it('increments occurrence_count on existing open issue', function (): void {
    $existingIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'status' => IssueStatus::Open,
        'occurrence_count' => 1,
    ]);

    Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'page_url' => $existingIssue->page_url,
        'detected_at' => now(),
    ]);

    $this->service->handle($this->scan);

    expect($existingIssue->fresh()->occurrence_count)->toBe(2);
});

it('updates last_detected_at on existing open issue', function (): void {
    $detectedAt = now()->subDay();

    $existingIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'status' => IssueStatus::Open,
        'last_detected_at' => now()->subWeek(),
    ]);

    Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'page_url' => $existingIssue->page_url,
        'detected_at' => $detectedAt,
    ]);

    $this->service->handle($this->scan);

    expect($existingIssue->fresh()->last_detected_at->toDateString())
        ->toBe($detectedAt->toDateString());
});

it('does not increment occurrence_count on a resolved issue', function (): void {
    $resolvedIssue = Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'status' => IssueStatus::Resolved,
        'occurrence_count' => 5,
    ]);

    Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'page_url' => $resolvedIssue->page_url,
        'detected_at' => now(),
    ]);

    $this->service->handle($this->scan);

    expect($resolvedIssue->fresh()->occurrence_count)->toBe(5)
        ->and(Issue::query()->count())->toBe(2);
});

it('maps FindingSeverity to correct IssueSeverity', function (): void {
    $severityMap = [
        FindingSeverity::CRITICAL->value => IssueSeverity::Critical,
        FindingSeverity::SERIOUS->value => IssueSeverity::High,
        FindingSeverity::MODERATE->value => IssueSeverity::Medium,
        FindingSeverity::MINOR->value => IssueSeverity::Low,
        FindingSeverity::INFO->value => IssueSeverity::Low,
    ];

    foreach ($severityMap as $findingSeverityValue => $expectedIssueSeverity) {
        Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
            'rule_key' => 'wcag-'.$findingSeverityValue,
            'severity' => FindingSeverity::from($findingSeverityValue),
            'page_url' => 'https://example.com/'.$findingSeverityValue,
            'detected_at' => now(),
        ]);
    }

    $this->service->handle($this->scan);

    foreach ($severityMap as $findingSeverityValue => $expectedIssueSeverity) {
        $issue = Issue::query()->where('rule_key', 'wcag-'.$findingSeverityValue)->first();

        expect($issue->severity)->toBe($expectedIssueSeverity);
    }
});

it('assigns correct risk_weight based on severity', function (): void {
    $riskWeightMap = [
        FindingSeverity::CRITICAL->value => 100,
        FindingSeverity::SERIOUS->value => 75,
        FindingSeverity::MODERATE->value => 50,
        FindingSeverity::MINOR->value => 25,
        FindingSeverity::INFO->value => 10,
    ];

    foreach ($riskWeightMap as $findingSeverityValue => $expectedRiskWeight) {
        Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
            'rule_key' => 'wcag-'.$findingSeverityValue,
            'severity' => FindingSeverity::from($findingSeverityValue),
            'page_url' => 'https://example.com/'.$findingSeverityValue,
            'detected_at' => now(),
        ]);
    }

    $this->service->handle($this->scan);

    foreach ($riskWeightMap as $findingSeverityValue => $expectedRiskWeight) {
        $issue = Issue::query()->where('rule_key', 'wcag-'.$findingSeverityValue)->first();

        expect($issue->risk_weight)->toBe($expectedRiskWeight);
    }
});

it('creates separate issues for different page_urls with same rule_key', function (): void {
    Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com/page-a',
        'detected_at' => now(),
    ]);

    Finding::factory()->for($this->agency)->for($this->scan)->for($this->property)->create([
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com/page-b',
        'detected_at' => now(),
    ]);

    $this->service->handle($this->scan);

    expect(Issue::query()->count())->toBe(2);
});

it('handles a scan with no findings', function (): void {
    $this->service->handle($this->scan);

    expect(Issue::query()->count())->toBe(0);
});
