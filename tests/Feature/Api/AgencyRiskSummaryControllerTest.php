<?php

use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;

// ── GET /api/{tenant}/risk-summary ───────────────────────────────────────────

it('Agency risk summary: returns agency data and null score when no snapshot exists', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/risk-summary")
        ->assertOk()
        ->assertJsonPath('agency.id', $agency->id)
        ->assertJsonPath('agency.slug', $agency->slug)
        ->assertJsonPath('risk_score', null)
        ->assertJsonPath('open_issue_count', null)
        ->assertJsonPath('snapshot_date', null)
        ->assertJsonStructure(['agency', 'risk_score', 'open_issue_count', 'snapshot_date', 'generated_at']);
});

it('Agency risk summary: returns the latest snapshot data when snapshots exist', function (): void {
    $agency = Agency::factory()->create();

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $agency->id,
        'risk_score' => 120,
        'open_issue_count' => 15,
        'snapshot_date' => '2024-01-01',
    ]);

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $agency->id,
        'risk_score' => 85,
        'open_issue_count' => 9,
        'snapshot_date' => '2024-02-01',
    ]);

    $this->getJson("/api/{$agency->slug}/risk-summary")
        ->assertOk()
        ->assertJsonPath('risk_score', 85)
        ->assertJsonPath('open_issue_count', 9)
        ->assertJsonPath('snapshot_date', '2024-02-01');
});

it('Agency risk summary: returns 404 for an unknown agency slug', function (): void {
    $this->getJson('/api/no-such-agency/risk-summary')
        ->assertNotFound();
});

it('Agency risk summary: does not leak data from another agency', function (): void {
    $agency = Agency::factory()->create();
    $other = Agency::factory()->create();

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $other->id,
        'risk_score' => 999,
        'open_issue_count' => 50,
        'snapshot_date' => '2024-01-15',
    ]);

    $this->getJson("/api/{$agency->slug}/risk-summary")
        ->assertOk()
        ->assertJsonPath('risk_score', null)
        ->assertJsonPath('open_issue_count', null);
});
