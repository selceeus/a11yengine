<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Jobs\GenerateGovernanceReportJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->actor = User::factory()->withRole(UserRoleEnum::AgencyAdmin, agencyId: $this->agency->id)->create(['agency_id' => $this->agency->id]);
});

// ── Authentication ────────────────────────────────────────────────────────────

it('requires authentication to generate a governance report', function (): void {
    $this->postJson(route('api.properties.governance-report.generate', $this->property), [
        'period_from' => now()->subDays(7)->toDateString(),
        'period_to' => now()->toDateString(),
    ])->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    Queue::fake();

    $this->actingAs($outsider)
        ->postJson(route('api.properties.governance-report.generate', $this->property), [
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->toDateString(),
        ])
        ->assertNotFound();
});

// ── Success ───────────────────────────────────────────────────────────────────

it('creates a pending GovernanceReport record', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.governance-report.generate', $this->property), [
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->toDateString(),
        ])
        ->assertStatus(202);

    $this->assertDatabaseHas('governance_reports', [
        'property_id' => $this->property->id,
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'status' => 'pending',
        'report_scope' => 'property',
        'is_scheduled' => false,
    ]);
});

it('dispatches a GenerateGovernanceReportJob for the property', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.governance-report.generate', $this->property), [
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->toDateString(),
        ])
        ->assertStatus(202);

    Queue::assertPushed(GenerateGovernanceReportJob::class, function (GenerateGovernanceReportJob $job): bool {
        return $job->report->property_id === $this->property->id;
    });
});

it('returns the report id and pending status in the response', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.governance-report.generate', $this->property), [
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->toDateString(),
        ])
        ->assertStatus(202)
        ->assertJsonStructure(['id', 'status'])
        ->assertJsonPath('status', 'pending');
});

it('validates that period_from must be before period_to', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.governance-report.generate', $this->property), [
            'period_from' => now()->toDateString(),
            'period_to' => now()->subDays(7)->toDateString(),
        ])
        ->assertUnprocessable();
});
