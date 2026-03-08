<?php

use App\Enums\FindingSeverity;
use App\Enums\ScanPageStatus;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\LighthouseResult;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->org = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'base_url' => 'https://example.com',
    ]);
});

// ─── Auth & authorization ─────────────────────────────────────────────────────

it('requires authentication', function (): void {
    $this->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $other = Agency::factory()->create();
    $otherUser = User::factory()->create(['agency_id' => $other->id]);

    $this->actingAs($otherUser)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertForbidden();
});

it('returns 404 for a non-existent site', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', 99999))
        ->assertNotFound();
});

// ─── Empty states ─────────────────────────────────────────────────────────────

it('returns an empty array when no completed scans exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertExactJson([]);
});

it('returns an empty array when only pending scans exist', function (): void {
    Scan::factory()->for($this->agency)->for($this->org)->for($this->property)->create([
        'status' => ScanStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertExactJson([]);
});

// ─── Response structure ───────────────────────────────────────────────────────

it('returns the correct per-page response structure', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/about',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonStructure([['url', 'riskScore', 'issueCount', 'lighthouseAccessibility']]);
});

// ─── URL stripping ────────────────────────────────────────────────────────────

it('strips the base_url to return a path-only url', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/about',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['url' => '/about']);
});

it('returns "/" for the root page', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['url' => '/']);
});

it('handles a trailing slash in base_url when stripping', function (): void {
    $this->property->update(['base_url' => 'https://example.com/']);

    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/contact',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['url' => '/contact']);
});

// ─── Risk score calculation ───────────────────────────────────────────────────

it('returns riskScore of 100 when a page has no findings', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/clean',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['riskScore' => 100]);
});

it('applies the correct severity weights to calculate riskScore', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/page',
    ]);

    // 1 critical (5) + 1 serious (3) + 1 moderate (1) + 1 minor (0.5) = 9.5
    // riskScore = round(100 - 9.5) = round(90.5) = 91
    foreach ([FindingSeverity::CRITICAL, FindingSeverity::SERIOUS, FindingSeverity::MODERATE, FindingSeverity::MINOR] as $severity) {
        Finding::factory()->for($this->agency)->for($this->property)->for($scan)->create([
            'severity' => $severity,
            'page_url' => 'https://example.com/page',
        ]);
    }

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['riskScore' => 91]);
});

it('clamps riskScore to a minimum of 0', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/bad',
    ]);

    // 21 critical findings × 5 = 105 weight → score would be -5, clamped to 0
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(21)->create([
        'severity' => FindingSeverity::CRITICAL,
        'page_url' => 'https://example.com/bad',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['riskScore' => 0]);
});

// ─── Issue count ──────────────────────────────────────────────────────────────

it('returns issueCount 0 when no findings have an issue_id', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/page',
    ]);

    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(3)->create([
        'page_url' => 'https://example.com/page',
        'issue_id' => null,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['issueCount' => 0]);
});

it('counts distinct issue_ids per page for issueCount', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/page',
    ]);

    $issue1 = Issue::factory()->for($this->agency)->for($this->property)->create();
    $issue2 = Issue::factory()->for($this->agency)->for($this->property)->create();

    // Two findings pointing to the same issue — should count as 1
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(2)->create([
        'page_url' => 'https://example.com/page',
        'issue_id' => $issue1->id,
    ]);

    // One finding for a second issue
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->create([
        'page_url' => 'https://example.com/page',
        'issue_id' => $issue2->id,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['issueCount' => 2]);
});

// ─── Lighthouse accessibility ─────────────────────────────────────────────────

it('returns lighthouseAccessibility of 0 when no lighthouse result exists for the page', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/page',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['lighthouseAccessibility' => 0]);
});

it('returns the lighthouse accessibility_score as an integer', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/page',
    ]);

    LighthouseResult::factory()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/page',
        'accessibility_score' => 82,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertJsonFragment(['lighthouseAccessibility' => 82]);
});

// ─── Sorting & limiting ───────────────────────────────────────────────────────

it('sorts pages by riskScore descending', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    // Page A: 1 critical finding → score = 95
    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create(['url' => 'https://example.com/a']);
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->create([
        'severity' => FindingSeverity::CRITICAL,
        'page_url' => 'https://example.com/a',
    ]);

    // Page B: no findings → score = 100
    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create(['url' => 'https://example.com/b']);

    // Page C: 2 critical findings → score = 90
    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->create(['url' => 'https://example.com/c']);
    Finding::factory()->for($this->agency)->for($this->property)->for($scan)->count(2)->create([
        'severity' => FindingSeverity::CRITICAL,
        'page_url' => 'https://example.com/c',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->json();

    expect($response[0]['url'])->toBe('/b')
        ->and($response[1]['url'])->toBe('/a')
        ->and($response[2]['url'])->toBe('/c');
});

it('limits the response to 200 pages', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($scan)->count(210)->create([
        'url' => fn () => 'https://example.com/page-'.fake()->unique()->numberBetween(1, 9999),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->json();

    expect($response)->toHaveCount(200);
});

// ─── Data isolation ───────────────────────────────────────────────────────────

it('only includes pages from the latest completed scan', function (): void {
    $latestScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now(),
    ]);
    $olderScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create([
        'completed_at' => now()->subDays(7),
    ]);

    ScanPage::factory()->scanned()->for($this->agency)->for($latestScan)->create(['url' => 'https://example.com/new']);
    ScanPage::factory()->scanned()->for($this->agency)->for($olderScan)->create(['url' => 'https://example.com/old']);

    $urls = collect(
        $this->actingAs($this->user)
            ->getJson(route('api.sites.risk-map', $this->property->id))
            ->assertOk()
            ->json()
    )->pluck('url');

    expect($urls)->toContain('/new')->not->toContain('/old');
});

it('does not include pages with a non-Scanned status', function (): void {
    $scan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($this->property)->create();

    ScanPage::factory()->for($this->agency)->for($scan)->create([
        'url' => 'https://example.com/failed',
        'status' => ScanPageStatus::Failed,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.sites.risk-map', $this->property->id))
        ->assertOk()
        ->assertExactJson([]);
});

it('does not include pages from a different property', function (): void {
    $other = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'base_url' => 'https://other.com',
    ]);

    $otherScan = Scan::factory()->completed()->for($this->agency)->for($this->org)->for($other)->create();

    ScanPage::factory()->scanned()->for($this->agency)->for($otherScan)->create(['url' => 'https://other.com/secret']);

    $urls = collect(
        $this->actingAs($this->user)
            ->getJson(route('api.sites.risk-map', $this->property->id))
            ->assertOk()
            ->json()
    )->pluck('url');

    expect($urls)->not->toContain('/secret');
});
