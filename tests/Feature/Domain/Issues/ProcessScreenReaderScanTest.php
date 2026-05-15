<?php

use App\Domain\Issues\ProcessHtmlScan;
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

function srPage(string $url, array $violations = []): array
{
    return ['url' => $url, 'violations' => $violations];
}

function srViolation(
    string $id = 'sr-missing-main-landmark',
    string $impact = 'serious',
    array $nodes = [],
    array $tags = ['wcag2a', 'wcag241', 'cat.structure'],
    string $helpUrl = 'https://www.w3.org/WAI/WCAG21/Understanding/bypass-blocks.html',
    string $description = 'Page has no main landmark',
): array {
    return [
        'id' => $id,
        'impact' => $impact,
        'description' => $description,
        'helpUrl' => $helpUrl,
        'tags' => $tags,
        'nodes' => empty($nodes) ? [srNode('#page')] : $nodes,
    ];
}

function srNode(string $selector, string $summary = 'Fix this SR issue'): array
{
    return [
        'target' => [$selector],
        'html' => "<div id=\"{$selector}\">",
        'failureSummary' => $summary,
    ];
}

// ─── Empty violations — no-op ─────────────────────────────────────────────────

it('does nothing when violations array is empty', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => []], updateScanPage: false);

    expect(Finding::query()->count())->toBe(0)
        ->and(Issue::query()->count())->toBe(0);
});

// ─── Findings ────────────────────────────────────────────────────────────────

it('creates a finding for each SR violation node', function (): void {
    $violations = [
        srViolation('sr-missing-main-landmark', 'serious', [srNode('#a'), srNode('#b')]),
        srViolation('sr-image-no-alt', 'critical', [srNode('#img')]),
    ];

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);

    expect(Finding::query()->count())->toBe(3);
});

it('stores the SR rule key as-is on the finding', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => [
        srViolation('sr-missing-h1', 'moderate', [srNode('#body')]),
    ]], updateScanPage: false);

    expect(Finding::query()->first()->rule_key)->toBe('sr-missing-h1');
});

it('stores the page url, element identifier, and message on the finding', function (): void {
    $node = srNode('#main-content', 'Add a <main> landmark.');

    $this->service->handle($this->scan, ['url' => 'https://example.com/about', 'violations' => [
        srViolation('sr-missing-main-landmark', 'serious', [$node]),
    ]], updateScanPage: false);

    $finding = Finding::query()->first();

    expect($finding->page_url)->toBe('https://example.com/about')
        ->and($finding->element_identifier)->toBe('#main-content')
        ->and($finding->message)->toBe('Add a <main> landmark.');
});

it('maps SR violation impact to FindingSeverity correctly', function (): void {
    $violations = [
        srViolation('sr-a', 'critical', [srNode('#a')]),
        srViolation('sr-b', 'serious', [srNode('#b')]),
        srViolation('sr-c', 'moderate', [srNode('#c')]),
        srViolation('sr-d', 'minor', [srNode('#d')]),
    ];

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);

    $severities = Finding::query()->orderBy('id')->pluck('severity');

    expect($severities[0])->toBe(FindingSeverity::CRITICAL)
        ->and($severities[1])->toBe(FindingSeverity::SERIOUS)
        ->and($severities[2])->toBe(FindingSeverity::MODERATE)
        ->and($severities[3])->toBe(FindingSeverity::MINOR);
});

// ─── Fingerprint deduplication ───────────────────────────────────────────────

it('does not create a duplicate finding when called twice with the same violation', function (): void {
    $violations = [srViolation('sr-missing-page-title', 'serious', [srNode('#title')])];

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);

    expect(Finding::query()->count())->toBe(1);
});

// ─── Issue normalisation ─────────────────────────────────────────────────────

it('creates an issue for each unique SR rule+page combination', function (): void {
    $violations = [
        srViolation('sr-missing-main-landmark', 'serious'),
        srViolation('sr-image-no-alt', 'critical'),
    ];

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);

    expect(Issue::query()->count())->toBe(2);
});

it('sets correct severity and risk_weight on the SR issue', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => [
        srViolation('sr-missing-main-landmark', 'serious'),
    ]], updateScanPage: false);

    $issue = Issue::query()->first();

    expect($issue->severity)->toBe(IssueSeverity::High)
        ->and($issue->risk_weight)->toBe(75);
});

it('increments occurrence_count when an open issue already exists for the same SR rule and page', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)->create([
        'rule_key' => 'sr-missing-main-landmark',
        'page_url' => 'https://example.com/',
        'status' => IssueStatus::Open,
        'occurrence_count' => 1,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => [
        srViolation('sr-missing-main-landmark', 'serious'),
    ]], updateScanPage: false);

    expect(Issue::query()->count())->toBe(1)
        ->and(Issue::query()->first()->occurrence_count)->toBe(2);
});

it('resolves SR tags to the correct WCAG category', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => [
        srViolation('sr-missing-lang-attribute', 'serious', [srNode('#html')], ['wcag311', 'wcag2a', 'cat.language']),
    ]], updateScanPage: false);

    expect(Finding::query()->first()->wcag_category)->toBe('understandable');
});
