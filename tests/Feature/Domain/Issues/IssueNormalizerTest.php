<?php

use App\Domain\Issues\IssueNormalizer;
use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;

// ─── Helpers ────────────────────────────────────────────────────────────────

function normalizerViolation(string $id, ?string $impact, array $tags = [], array $nodes = []): array
{
    return [
        'id' => $id,
        'impact' => $impact,
        'tags' => $tags,
        'nodes' => empty($nodes) ? [normalizerNode('#default')] : $nodes,
    ];
}

function normalizerNode(string $selector, ?string $html = null, ?string $summary = null): array
{
    return [
        'target' => [$selector],
        'html' => $html ?? "<div>{$selector}</div>",
        'failureSummary' => $summary ?? 'Fix this issue.',
    ];
}

// ─── Rule ID ─────────────────────────────────────────────────────────────────

it('preserves the axe-core rule id', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('color-contrast', 'serious'));

    expect($result['rule_id'])->toBe('color-contrast');
});

// ─── WCAG category resolution ─────────────────────────────────────────────────

it('maps wcag1* tags to the perceivable category', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('image-alt', 'serious', ['wcag1aa', 'ACT']));

    expect($result['wcag_category'])->toBe('perceivable');
});

it('maps wcag2* tags to the operable category', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('keyboard-access', 'serious', ['wcag2a', 'wcag2aa']));

    expect($result['wcag_category'])->toBe('operable');
});

it('maps wcag3* tags to the understandable category', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('label', 'moderate', ['wcag3a']));

    expect($result['wcag_category'])->toBe('understandable');
});

it('maps wcag4* tags to the robust category', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('aria-allowed-attr', 'serious', ['wcag4a', 'wcag4aa']));

    expect($result['wcag_category'])->toBe('robust');
});

it('falls back to best-practice when no wcag tags are present', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('scrollable-region-focusable', 'moderate', ['best-practice', 'ACT']));

    expect($result['wcag_category'])->toBe('best-practice');
});

it('falls back to best-practice when tags array is empty', function (): void {
    $normalizer = new IssueNormalizer;

    $result = $normalizer->normalize(normalizerViolation('some-rule', 'minor', []));

    expect($result['wcag_category'])->toBe('best-practice');
});

// ─── Severity mapping ─────────────────────────────────────────────────────────

it('maps critical impact to CRITICAL finding severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'critical'));

    expect($result['severity'])->toBe(FindingSeverity::CRITICAL);
});

it('maps serious impact to SERIOUS finding severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'serious'));

    expect($result['severity'])->toBe(FindingSeverity::SERIOUS);
});

it('maps moderate impact to MODERATE finding severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'moderate'));

    expect($result['severity'])->toBe(FindingSeverity::MODERATE);
});

it('maps minor impact to MINOR finding severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'minor'));

    expect($result['severity'])->toBe(FindingSeverity::MINOR);
});

it('maps null impact to INFO finding severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', null));

    expect($result['severity'])->toBe(FindingSeverity::INFO);
});

it('maps unknown impact strings to INFO finding severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'unknown-level'));

    expect($result['severity'])->toBe(FindingSeverity::INFO);
});

// ─── Issue severity mapping ───────────────────────────────────────────────────

it('maps critical finding severity to Critical issue severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'critical'));

    expect($result['issue_severity'])->toBe(IssueSeverity::Critical);
});

it('maps serious finding severity to High issue severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'serious'));

    expect($result['issue_severity'])->toBe(IssueSeverity::High);
});

it('maps moderate finding severity to Medium issue severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'moderate'));

    expect($result['issue_severity'])->toBe(IssueSeverity::Medium);
});

it('maps minor finding severity to Low issue severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'minor'));

    expect($result['issue_severity'])->toBe(IssueSeverity::Low);
});

it('maps info finding severity to Low issue severity', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', null));

    expect($result['issue_severity'])->toBe(IssueSeverity::Low);
});

// ─── Risk weight ─────────────────────────────────────────────────────────────

it('assigns a risk weight of 100 for critical impact', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'critical'));

    expect($result['risk_weight'])->toBe(100);
});

it('assigns a risk weight of 75 for serious impact', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'serious'));

    expect($result['risk_weight'])->toBe(75);
});

it('assigns a risk weight of 50 for moderate impact', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'moderate'));

    expect($result['risk_weight'])->toBe(50);
});

it('assigns a risk weight of 25 for minor impact', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'minor'));

    expect($result['risk_weight'])->toBe(25);
});

it('assigns a risk weight of 10 for null impact', function (): void {
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', null));

    expect($result['risk_weight'])->toBe(10);
});

// ─── Element metadata extraction ─────────────────────────────────────────────

it('extracts the element identifier from the first target selector', function (): void {
    $nodes = [normalizerNode('#main-nav')];
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'serious', [], $nodes));

    expect($result['elements'][0]['identifier'])->toBe('#main-nav');
});

it('extracts the html snippet for each element', function (): void {
    $nodes = [normalizerNode('#btn', '<button id="btn">Submit</button>')];
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'serious', [], $nodes));

    expect($result['elements'][0]['html'])->toBe('<button id="btn">Submit</button>');
});

it('extracts the failure summary for each element', function (): void {
    $nodes = [normalizerNode('#img', null, 'Element does not have an alt attribute.')];
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'serious', [], $nodes));

    expect($result['elements'][0]['failure_summary'])->toBe('Element does not have an alt attribute.');
});

it('returns one element entry per violation node', function (): void {
    $nodes = [normalizerNode('#a'), normalizerNode('#b'), normalizerNode('#c')];
    $result = (new IssueNormalizer)->normalize(normalizerViolation('rule', 'moderate', [], $nodes));

    expect($result['elements'])->toHaveCount(3);
});

it('returns an empty elements list when nodes are absent', function (): void {
    $violation = ['id' => 'rule', 'impact' => 'minor', 'tags' => [], 'nodes' => []];
    $result = (new IssueNormalizer)->normalize($violation);

    expect($result['elements'])->toBeEmpty();
});

it('stores null html when the html key is missing from a node', function (): void {
    $node = ['target' => ['#el'], 'failureSummary' => 'Fix this.'];
    $result = (new IssueNormalizer)->normalize(['id' => 'rule', 'impact' => 'minor', 'tags' => [], 'nodes' => [$node]]);

    expect($result['elements'][0]['html'])->toBeNull();
});

it('stores null failure_summary when the failureSummary key is missing from a node', function (): void {
    $node = ['target' => ['#el'], 'html' => '<div></div>'];
    $result = (new IssueNormalizer)->normalize(['id' => 'rule', 'impact' => 'minor', 'tags' => [], 'nodes' => [$node]]);

    expect($result['elements'][0]['failure_summary'])->toBeNull();
});
