<?php

use App\Ai\Agents\GovernanceAgent;
use App\Domain\Governance\AiGovernanceService;
use App\Enums\GovernanceReportStatus;
use App\Jobs\GenerateGovernanceReportJob;
use App\Models\Agency;
use App\Models\GovernanceReport;
use App\Models\Organization;
use App\Models\Property;
use Laravel\Ai\Ai;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->report = GovernanceReport::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => GovernanceReportStatus::Pending,
    ]);
});

// ── Status transition ─────────────────────────────────────────────────────────

it('transitions the report to Processing then Completed on success', function (): void {
    Ai::fakeAgent(GovernanceAgent::class, [[
        'executive_narrative' => 'Test narrative.',
        'summary_cards' => [],
        'recommendations' => [],
    ]]);

    $job = new GenerateGovernanceReportJob($this->report);
    $job->handle(app(AiGovernanceService::class));

    // After synchronous handle(), the report must have reached Completed
    expect($this->report->fresh()->status)->toBe(GovernanceReportStatus::Completed);
});

it('transitions the report to Completed after successful generation', function (): void {
    $aiResponse = json_encode([
        'executive_narrative' => 'The site has improved accessibility significantly.',
        'summary_cards' => [
            ['title' => 'Open Issues', 'value' => 12, 'delta' => -3, 'trend' => 'up', 'unit' => null],
        ],
        'recommendations' => [
            [
                'priority' => 'high',
                'title' => 'Fix critical image alt text issues',
                'rationale' => 'Critical issues block screen reader users.',
                'category' => 'Images',
                'action' => 'Add descriptive alt text to all images.',
                'due_by_quarter' => 'Q2 2025',
                'source_refs' => [],
            ],
        ],
    ]);

    Ai::fakeAgent(GovernanceAgent::class, [json_decode($aiResponse, true)]);

    (new GenerateGovernanceReportJob($this->report))->handle(app(AiGovernanceService::class));

    $fresh = $this->report->fresh();
    expect($fresh->status)->toBe(GovernanceReportStatus::Completed)
        ->and($fresh->executive_narrative)->toBe('The site has improved accessibility significantly.')
        ->and($fresh->summary_cards)->toHaveCount(1)
        ->and($fresh->recommendations)->toHaveCount(1)
        ->and($fresh->generated_at)->not->toBeNull();
});

// ── AI call ───────────────────────────────────────────────────────────────────

it('stores the prompt_context and raw_ai_response', function (): void {
    $rawPayload = json_encode([
        'executive_narrative' => 'Narrative.',
        'summary_cards' => [],
        'recommendations' => [],
    ]);

    Ai::fakeAgent(GovernanceAgent::class, [json_decode($rawPayload, true)]);

    (new GenerateGovernanceReportJob($this->report))->handle(app(AiGovernanceService::class));

    $fresh = $this->report->fresh();
    expect($fresh->prompt_context)->not->toBeNull()
        ->and($fresh->raw_ai_response)->toBe($rawPayload);
});

it('persists legal_risk_rating and legal_precedents from the AI response', function (): void {
    $precedents = [
        [
            'case_name' => 'Gil v. Winn-Dixie Stores, Inc.',
            'year' => 2017,
            'outcome' => 'plaintiff_won',
            'relevance' => 'Inaccessible website violated ADA.',
        ],
        [
            'case_name' => 'Robles v. Domino\'s Pizza LLC',
            'year' => 2019,
            'outcome' => 'plaintiff_won',
            'relevance' => 'Ninth Circuit upheld ADA applies to websites.',
        ],
    ];

    Ai::fakeAgent(GovernanceAgent::class, [[
        'executive_narrative' => 'Legal risk is elevated.',
        'summary_cards' => [],
        'legal_risk_rating' => 'high',
        'legal_precedents' => $precedents,
        'recommendations' => [],
    ]]);

    (new GenerateGovernanceReportJob($this->report))->handle(app(AiGovernanceService::class));

    $fresh = $this->report->fresh();
    expect($fresh->legal_risk_rating)->toBe('high')
        ->and($fresh->legal_precedents)->toHaveCount(2)
        ->and($fresh->legal_precedents[0]['case_name'])->toBe('Gil v. Winn-Dixie Stores, Inc.')
        ->and($fresh->legal_precedents[1]['outcome'])->toBe('plaintiff_won');
});

it('defaults legal_risk_rating to null when AI omits it', function (): void {
    Ai::fakeAgent(GovernanceAgent::class, [[
        'executive_narrative' => 'No legal data.',
        'summary_cards' => [],
        'recommendations' => [],
    ]]);

    (new GenerateGovernanceReportJob($this->report))->handle(app(AiGovernanceService::class));

    $fresh = $this->report->fresh();
    expect($fresh->legal_risk_rating)->toBeNull()
        ->and($fresh->legal_precedents)->toBe([]);
});

// ── Failure ───────────────────────────────────────────────────────────────────

it('transitions to Failed when the job fails', function (): void {
    (new GenerateGovernanceReportJob($this->report))->failed(new RuntimeException('API timeout'));

    $fresh = $this->report->fresh();
    expect($fresh->status)->toBe(GovernanceReportStatus::Failed)
        ->and($fresh->error_message)->toContain('API timeout');
});

it('truncates the error message to 250 characters', function (): void {
    $longMessage = str_repeat('x', 300);

    (new GenerateGovernanceReportJob($this->report))->failed(new RuntimeException($longMessage));

    expect($this->report->fresh()->error_message)->toHaveLength(250);
});
