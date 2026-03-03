<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\OrganizationRiskSnapshot;
use App\Models\Property;
use App\Models\RiskSnapshot;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();
    $this->service = app(ProcessHtmlScan::class);
});

// ─── Helpers ────────────────────────────────────────────────────────────────

function axePage(string $url, array $violations = []): array
{
    return ['url' => $url, 'violations' => $violations];
}

function axeViolation(string $id, string $impact, array $nodes = []): array
{
    return [
        'id' => $id,
        'impact' => $impact,
        'nodes' => empty($nodes) ? [axeNode('#default')] : $nodes,
    ];
}

function axeNode(string $selector, string $summary = 'Fix this issue'): array
{
    return [
        'target' => [$selector],
        'html' => "<div id=\"{$selector}\">...</div>",
        'failureSummary' => $summary,
    ];
}

// ─── Findings ────────────────────────────────────────────────────────────────

it('creates a finding for each violation node', function (): void {
    $pageResult = axePage('https://example.com/page', [
        axeViolation('color-contrast', 'serious', [axeNode('#header'), axeNode('#footer')]),
        axeViolation('image-alt', 'critical', [axeNode('#logo')]),
    ]);

    $this->service->handle($this->scan, $pageResult);

    expect(Finding::query()->count())->toBe(3);
});

it('maps axe-core impact to FindingSeverity correctly', function (): void {
    $pageResult = axePage('https://example.com/page', [
        axeViolation('rule-a', 'critical', [axeNode('#a')]),
        axeViolation('rule-b', 'serious', [axeNode('#b')]),
        axeViolation('rule-c', 'moderate', [axeNode('#c')]),
        axeViolation('rule-d', 'minor', [axeNode('#d')]),
    ]);

    $this->service->handle($this->scan, $pageResult);

    $severities = Finding::query()->orderBy('id')->pluck('severity');

    expect($severities[0])->toBe(FindingSeverity::CRITICAL)
        ->and($severities[1])->toBe(FindingSeverity::SERIOUS)
        ->and($severities[2])->toBe(FindingSeverity::MODERATE)
        ->and($severities[3])->toBe(FindingSeverity::MINOR);
});

it('falls back to INFO severity when impact is null', function (): void {
    $violation = ['id' => 'custom-rule', 'impact' => null, 'nodes' => [axeNode('#x')]];

    $this->service->handle($this->scan, axePage('https://example.com', [$violation]));

    expect(Finding::query()->first()->severity)->toBe(FindingSeverity::INFO);
});

it('stores the element identifier from the first target selector', function (): void {
    $node = ['target' => ['#my-element'], 'failureSummary' => 'Fix me'];

    $this->service->handle($this->scan, axePage('https://example.com', [
        ['id' => 'label', 'impact' => 'serious', 'nodes' => [$node]],
    ]));

    expect(Finding::query()->first()->element_identifier)->toBe('#my-element');
});

it('stores the page url, rule key, and message on the finding', function (): void {
    $node = ['target' => ['#el'], 'failureSummary' => 'Fix the label'];

    $this->service->handle($this->scan, axePage('https://example.com/about', [
        ['id' => 'label', 'impact' => 'moderate', 'nodes' => [$node]],
    ]));

    $finding = Finding::query()->first();

    expect($finding->page_url)->toBe('https://example.com/about')
        ->and($finding->rule_key)->toBe('label')
        ->and($finding->message)->toBe('Fix the label');
});

// ─── Issue normalisation ─────────────────────────────────────────────────────

it('creates a new issue for each unique rule+page combination', function (): void {
    $pageResult = axePage('https://example.com/page', [
        axeViolation('color-contrast', 'serious'),
        axeViolation('image-alt', 'critical'),
    ]);

    $this->service->handle($this->scan, $pageResult);

    expect(Issue::query()->count())->toBe(2);
});

it('sets the correct severity and risk_weight on the issue', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com', [
        axeViolation('color-contrast', 'serious'),
    ]));

    $issue = Issue::query()->first();

    expect($issue->severity)->toBe(IssueSeverity::High)
        ->and($issue->risk_weight)->toBe(75);
});

it('increments occurrence_count when an open issue already exists for the same rule and page', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'rule_key' => 'color-contrast',
        'page_url' => 'https://example.com/page',
        'status' => IssueStatus::Open,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $this->service->handle($this->scan, axePage('https://example.com/page', [
        axeViolation('color-contrast', 'serious'),
    ]));

    expect(Issue::query()->count())->toBe(1)
        ->and(Issue::query()->first()->occurrence_count)->toBe(3);
});

it('links the finding to the issue via issue_id', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com', [
        axeViolation('color-contrast', 'serious'),
    ]));

    $finding = Finding::query()->first();
    $issue = Issue::query()->first();

    expect($finding->issue_id)->toBe($issue->id);
});

it('does not create a duplicate issue for a resolved issue with the same rule and page', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'rule_key' => 'image-alt',
        'page_url' => 'https://example.com',
        'status' => IssueStatus::Resolved,
        'occurrence_count' => 1,
        'first_detected_at' => now()->subDay(),
        'last_detected_at' => now()->subDay(),
    ]);

    $this->service->handle($this->scan, axePage('https://example.com', [
        axeViolation('image-alt', 'critical'),
    ]));

    // New open issue created; resolved one stays separate
    expect(Issue::query()->count())->toBe(2)
        ->and(Issue::query()->where('status', IssueStatus::Open)->count())->toBe(1);
});

// ─── ScanPage ────────────────────────────────────────────────────────────────

it('records a scan page with the url and violation count', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com/page', [
        axeViolation('color-contrast', 'serious', [axeNode('#a'), axeNode('#b')]),
    ]));

    $page = ScanPage::query()->first();

    expect($page)->not->toBeNull()
        ->and($page->url)->toBe('https://example.com/page')
        ->and($page->violations_count)->toBe(2);
});

it('records a scan page with zero violations when the page is clean', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com/clean'));

    $page = ScanPage::query()->first();

    expect($page)->not->toBeNull()
        ->and($page->violations_count)->toBe(0);
});

it('returns the ScanPage instance', function (): void {
    $result = $this->service->handle($this->scan, axePage('https://example.com'));

    expect($result)->toBeInstanceOf(ScanPage::class);
});

// ─── Risk + governance recalculation ─────────────────────────────────────────

it('records a risk snapshot after processing a page', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com', [
        axeViolation('color-contrast', 'serious'),
    ]));

    expect(RiskSnapshot::query()->where('organization_id', $this->organization->id)->count())->toBe(1);
});

it('records an organization risk snapshot after processing a page', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com', [
        axeViolation('image-alt', 'critical'),
    ]));

    expect(OrganizationRiskSnapshot::query()->where('organization_id', $this->organization->id)->count())->toBe(1);
});

it('reflects open issues in the risk snapshot score', function (): void {
    $this->service->handle($this->scan, axePage('https://example.com', [
        axeViolation('color-contrast', 'serious'), // risk_weight=75, occurrence=1
    ]));

    $snapshot = RiskSnapshot::query()->where('organization_id', $this->organization->id)->first();

    expect($snapshot->total_risk_score)->toBe(75);
});
