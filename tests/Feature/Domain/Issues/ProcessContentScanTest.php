<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Enums\FindingSeverity;
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

function contentViolation(
    string $id = 'content-multiple-h1',
    string $impact = 'moderate',
    array $nodes = [],
    array $tags = ['wcag246', 'wcag2aa', 'cat.structure'],
    string $helpUrl = 'https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html',
    string $description = 'Page contains more than one <h1> heading.',
): array {
    return [
        'id' => $id,
        'impact' => $impact,
        'description' => $description,
        'helpUrl' => $helpUrl,
        'tags' => $tags,
        'nodes' => empty($nodes) ? [contentNode('h1')] : $nodes,
    ];
}

function contentNode(string $selector, string $summary = 'Fix this content issue'): array
{
    return [
        'target' => [$selector],
        'html' => "<{$selector}>Example</{$selector}>",
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

it('creates a finding for each content violation node', function (): void {
    $violations = [
        contentViolation('content-multiple-h1', 'moderate', [contentNode('h1:nth-child(1)'), contentNode('h1:nth-child(2)')]),
        contentViolation('content-img-generic-alt', 'moderate', [contentNode('img')]),
    ];

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);

    expect(Finding::query()->count())->toBe(3);
});

it('stores the content rule key as-is on the finding', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => [
        contentViolation('content-link-url-as-text', 'moderate', [contentNode('a')]),
    ]], updateScanPage: false);

    expect(Finding::query()->first()->rule_key)->toBe('content-link-url-as-text');
});

it('stores the page url on the finding', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/about', 'violations' => [
        contentViolation('content-multiple-h1', 'moderate', [contentNode('h1')]),
    ]], updateScanPage: false);

    expect(Finding::query()->first()->page_url)->toBe('https://example.com/about');
});

// ─── Issues ──────────────────────────────────────────────────────────────────

it('creates an issue for a content violation', function (): void {
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => [
        contentViolation('content-generic-page-title', 'serious', [contentNode('title')]),
    ]], updateScanPage: false);

    expect(Issue::query()->count())->toBe(1);
});

it('does not create duplicate issues for the same rule on the same page', function (): void {
    $violations = [
        contentViolation('content-multiple-h1', 'moderate', [contentNode('h1'), contentNode('h1')]),
    ];

    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);
    $this->service->handle($this->scan, ['url' => 'https://example.com/', 'violations' => $violations], updateScanPage: false);

    expect(Issue::query()->where('rule_key', 'content-multiple-h1')->count())->toBe(1);
});

// ─── Video rule WCAG mapping ──────────────────────────────────────────────────

it('creates a finding for video missing captions violation', function (): void {
    $violations = [
        contentViolation(
            'content-video-missing-captions',
            'critical',
            [contentNode('video')],
            ['wcag122', 'wcag2aa', 'cat.time-and-media'],
            'https://www.w3.org/WAI/WCAG21/Understanding/captions-prerecorded.html',
            'HTML5 video element has no captions track.',
        ),
    ];

    $this->service->handle($this->scan, ['url' => 'https://example.com/video', 'violations' => $violations], updateScanPage: false);

    $finding = Finding::query()->first();
    expect($finding->rule_key)->toBe('content-video-missing-captions')
        ->and($finding->severity)->toBe(FindingSeverity::CRITICAL);
});
