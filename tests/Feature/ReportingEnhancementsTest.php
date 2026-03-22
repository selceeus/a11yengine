<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\ContentAudit;
use App\Models\Finding;
use App\Models\GovernanceReport;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RiskAdvisory;
use App\Models\Scan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// ── Helpers ─────────────────────────────────────────────────────────────────

function createTenantUser(): array
{
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()->create(['agency_id' => $agency->id]);
    app()->instance(Agency::class, $agency);

    return [$user, $agency, $organization, $property];
}

// ── Org API: Issue Summary ──────────────────────────────────────────────────

it('returns issue summary for an organization', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);
    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Low,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.organizations.issues.summary', $organization))
        ->assertOk()
        ->assertJsonStructure(['critical', 'high', 'medium', 'low', 'total', 'generated_at'])
        ->assertJson(['critical' => 1, 'low' => 1, 'total' => 2]);
});

// ── Org API: Scan Activity ──────────────────────────────────────────────────

it('returns scan activity for an organization', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.organizations.scans.activity', $organization))
        ->assertOk()
        ->assertJsonStructure(['days', 'generated_at']);
});

// ── Org API: Risk Trends ────────────────────────────────────────────────────

it('returns risk trends for an organization', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $this->actingAs($user)
        ->getJson(route('api.organizations.risk-trends', $organization))
        ->assertOk()
        ->assertJsonStructure(['organization', 'series', 'days', 'generated_at']);
});

// ── Org API: Top Risk Properties ────────────────────────────────────────────

it('returns top risk properties for an organization', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $this->actingAs($user)
        ->getJson(route('api.organizations.properties.top-risk', $organization))
        ->assertOk()
        ->assertJsonStructure(['properties', 'generated_at']);
});

// ── Organization Show: enriched stats ───────────────────────────────────────

it('displays organization show page with stats', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $this->actingAs($user)
        ->get(route('organizations.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/show')
            ->has('organization')
            ->has('stats')
            ->has('recentScans')
        );
});

// ── Risk Advisory: show page ────────────────────────────────────────────────

it('displays a risk advisory show page', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $advisory = RiskAdvisory::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get(route('risk-advisory.show', $advisory))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('risk-advisory/show')
            ->has('advisory')
            ->where('advisory.id', $advisory->id)
        );
});

// ── Risk Advisory: export JSON ──────────────────────────────────────────────

it('exports a risk advisory as JSON', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $advisory = RiskAdvisory::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get(route('risk-advisory.export', [$advisory, 'json']))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['id', 'priorities', 'generated_at']);
});

// ── Risk Advisory: export CSV ───────────────────────────────────────────────

it('exports a risk advisory as CSV', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $advisory = RiskAdvisory::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('risk-advisory.export', [$advisory, 'csv']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

// ── Content Audit: show page ────────────────────────────────────────────────

it('displays a content audit show page', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $audit = ContentAudit::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get(route('content-audit.show', $audit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('content-audit/show')
            ->has('audit')
            ->where('audit.id', $audit->id)
        );
});

// ── Content Audit: export JSON ──────────────────────────────────────────────

it('exports a content audit as JSON', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $audit = ContentAudit::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get(route('content-audit.export', [$audit, 'json']))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['id', 'content_issues', 'generated_at']);
});

// ── Content Audit: export CSV ───────────────────────────────────────────────

it('exports a content audit as CSV', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $audit = ContentAudit::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('content-audit.export', [$audit, 'csv']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

// ── Governance Report: export JSON ──────────────────────────────────────────

it('exports a governance report as JSON', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $report = GovernanceReport::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get(route('governance.export', [$report, 'json']))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['id', 'executive_narrative', 'recommendations']);
});

// ── Governance Report: export CSV ───────────────────────────────────────────

it('exports a governance report as CSV', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $report = GovernanceReport::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('governance.export', [$report, 'csv']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

// ── Scan: export JSON ───────────────────────────────────────────────────────

it('exports scan findings as JSON', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    Finding::factory()->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($user)
        ->get(route('scans.export', [$scan, 'json']))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['scan_id', 'findings', 'lighthouse']);
});

// ── Scan: export CSV ────────────────────────────────────────────────────────

it('exports scan findings as CSV', function (): void {
    [$user, $agency, $organization, $property] = createTenantUser();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    Finding::factory()->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('scans.export', [$scan, 'csv']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

// ── Guests cannot access reporting endpoints ────────────────────────────────

it('redirects guests from org API endpoints', function (): void {
    $organization = Organization::factory()->create();

    $this->getJson(route('api.organizations.issues.summary', $organization))
        ->assertUnauthorized();
});

it('redirects guests from risk advisory show', function (): void {
    $advisory = RiskAdvisory::factory()->completed()->create();

    $this->get(route('risk-advisory.show', $advisory))
        ->assertRedirect();
});

it('redirects guests from content audit show', function (): void {
    $audit = ContentAudit::factory()->completed()->create();

    $this->get(route('content-audit.show', $audit))
        ->assertRedirect();
});
