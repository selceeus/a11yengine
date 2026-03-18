<?php

use App\Enums\AuditStatus;
use App\Jobs\GenerateAiAuditJob;
use App\Models\Agency;
use App\Models\Audit;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);

    Queue::fake();
});

// --- index -------------------------------------------------------------------

it('returns the audits index page', function (): void {
    $this->get(route('audits.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('audits/index'));
});

it('passes paginated audits belonging to the agency', function (): void {
    Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->count(3)
        ->create();

    $this->get(route('audits.index'))
        ->assertInertia(fn ($page) => $page->has('audits.data', 3));
});

it('does not expose audits from another agency on the index', function (): void {
    Audit::factory()->count(2)->create();

    Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->get(route('audits.index'))
        ->assertInertia(fn ($page) => $page->has('audits.data', 1));
});

it('redirects unauthenticated users from the index', function (): void {
    $this->post('/logout');

    $this->get(route('audits.index'))->assertRedirect(route('login'));
});

// --- store -------------------------------------------------------------------

it('creates an audit and dispatches GenerateAiAuditJob', function (): void {
    $this->post(route('audits.store'), [
        'property_id' => $this->property->id,
        'title' => 'My Test Audit',
    ])->assertRedirect();

    $this->assertDatabaseHas('audits', [
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'title' => 'My Test Audit',
        'status' => AuditStatus::Pending->value,
    ]);

    Queue::assertPushed(GenerateAiAuditJob::class);
});

it('auto-generates a title when none is provided', function (): void {
    $this->post(route('audits.store'), [
        'property_id' => $this->property->id,
    ])->assertRedirect();

    $audit = Audit::withoutGlobalScopes()->where('property_id', $this->property->id)->first();
    expect($audit->title)->toStartWith('AI Audit');
});

it('returns a validation error when property_id is missing', function (): void {
    $this->post(route('audits.store'), [])->assertSessionHasErrors('property_id');

    Queue::assertNothingPushed();
});

it('returns 404 when store targets a property from another agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->post(route('audits.store'), [
        'property_id' => $otherProperty->id,
    ])->assertNotFound();

    Queue::assertNothingPushed();
});

// --- show --------------------------------------------------------------------

it('returns the audit show page', function (): void {
    $audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create();

    $this->get(route('audits.show', $audit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('audits/show'));
});

it('returns 404 when viewing an audit from another agency', function (): void {
    $otherAudit = Audit::factory()->create();

    $this->get(route('audits.show', $otherAudit))->assertNotFound();
});

// --- destroy -----------------------------------------------------------------

it('deletes an audit and redirects to index', function (): void {
    $audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->delete(route('audits.destroy', $audit))
        ->assertRedirect(route('audits.index'));

    $this->assertDatabaseMissing('audits', ['id' => $audit->id]);
});

it('returns 404 when deleting an audit from another agency', function (): void {
    $otherAudit = Audit::factory()->create();

    $this->delete(route('audits.destroy', $otherAudit))->assertNotFound();
});

// --- export ------------------------------------------------------------------

it('exports a completed audit as JSON', function (): void {
    $audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create();

    $this->get(route('audits.export', ['audit' => $audit, 'format' => 'json']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json');
});

it('exports a completed audit as CSV', function (): void {
    $audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create();

    $this->get(route('audits.export', ['audit' => $audit, 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('exports a completed audit as PDF (HTML)', function (): void {
    $audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create();

    $this->get(route('audits.export', ['audit' => $audit, 'format' => 'pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8');
});
