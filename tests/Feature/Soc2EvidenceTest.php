<?php

use App\Enums\UserRole;
use App\Models\AccessReview;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

it('exports user roles as CSV', function (): void {
    $response = $this->actingAs($this->admin)
        ->get('/settings/soc2-evidence/export/user-roles');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain($this->admin->email);
});

it('exports api key inventory as CSV', function (): void {
    ApiKey::factory()->create(['agency_id' => $this->agency->id, 'name' => 'My Key']);

    $response = $this->actingAs($this->admin)
        ->get('/settings/soc2-evidence/export/api-keys');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('My Key');
});

it('exports access reviews as CSV', function (): void {
    AccessReview::factory()->create([
        'agency_id' => $this->agency->id,
        'period' => '2025-Q4',
        'status' => 'completed',
        'completed_at' => now(),
        'completed_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/settings/soc2-evidence/export/access-reviews');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('2025-Q4');
});
