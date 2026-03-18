<?php

use App\Models\Agency;
use App\Models\Audit;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
});

it('requires authentication', function (): void {
    $this->getJson(route('api.properties.audits.trend', $this->property))
        ->assertUnauthorized();
});

it('returns 404 for a property belonging to another agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $otherProperty))
        ->assertNotFound();
});

it('returns 422 for an invalid days parameter', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $this->property).'?days=14')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('days');
});

it('accepts valid days values', function (int $days): void {
    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $this->property)."?days={$days}")
        ->assertOk();
})->with([7, 30, 90]);

it('returns the expected response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $this->property))
        ->assertOk()
        ->assertJsonStructure(['history', 'audit_count', 'previous_score', 'score_delta', 'trend_direction', 'property_id', 'days', 'generated_at']);
});

it('returns empty history when no completed audits exist for the property', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $this->property))
        ->assertOk()
        ->assertJson(['history' => [], 'audit_count' => 0]);
});

it('includes completed audits within the requested window', function (): void {
    Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create(['generated_at' => now()->subDays(5)]);

    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $this->property).'?days=7')
        ->assertOk()
        ->assertJson(['audit_count' => 1, 'property_id' => $this->property->id]);
});

it('excludes audits outside the requested window', function (): void {
    Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create(['generated_at' => now()->subDays(10)]);

    $this->actingAs($this->user)
        ->getJson(route('api.properties.audits.trend', $this->property).'?days=7')
        ->assertOk()
        ->assertJson(['audit_count' => 0]);
});
