<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Jobs\GenerateContentAuditJob;
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

it('requires authentication to generate a content audit', function (): void {
    $this->postJson(route('api.properties.content-audit.generate', $this->property))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    Queue::fake();

    $this->actingAs($outsider)
        ->postJson(route('api.properties.content-audit.generate', $this->property))
        ->assertNotFound();
});

// ── Success ───────────────────────────────────────────────────────────────────

it('creates a pending ContentAudit record', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.content-audit.generate', $this->property))
        ->assertStatus(202);

    $this->assertDatabaseHas('content_audits', [
        'property_id' => $this->property->id,
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'status' => 'pending',
    ]);
});

it('dispatches a GenerateContentAuditJob for the property', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.content-audit.generate', $this->property))
        ->assertStatus(202);

    Queue::assertPushed(GenerateContentAuditJob::class, function (GenerateContentAuditJob $job): bool {
        return $job->contentAudit->property_id === $this->property->id;
    });
});

it('returns the audit id and pending status in the response', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.content-audit.generate', $this->property))
        ->assertStatus(202)
        ->assertJsonStructure(['id', 'status'])
        ->assertJsonPath('status', 'pending');
});
