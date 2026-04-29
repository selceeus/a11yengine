<?php

use App\Ai\Agents\AuditAgent;
use App\Enums\AuditStatus;
use App\Jobs\GenerateAiAuditJob;
use App\Models\Agency;
use App\Models\Audit;
use App\Models\Organization;
use App\Models\Property;
use Laravel\Ai\Ai;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->audit = Audit::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();
});

// ─── status transitions ───────────────────────────────────────────────────────

it('transitions status from Pending through Processing to Completed on success', function (): void {
    $aiJson = json_encode([
        'overall_score' => 78,
        'executive_summary' => 'Good accessibility.',
        'compliance_status' => ['wcag_a' => 'pass', 'wcag_aa' => 'partial', 'wcag_aaa' => 'fail'],
        'summary_statistics' => ['total_issues' => 10],
        'top_risks' => [],
        'issue_details' => [],
        'remediations' => [],
    ]);

    Ai::fakeAgent(AuditAgent::class, [json_decode($aiJson, true)]);

    (new GenerateAiAuditJob($this->audit))->handle(app(\App\Services\AiAuditService::class));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(AuditStatus::Completed)
        ->and($fresh->overall_score)->toBe(78)
        ->and($fresh->generated_at)->not->toBeNull();
});

it('populates executive_summary from AI response', function (): void {
    $aiJson = json_encode([
        'overall_score' => 60,
        'executive_summary' => 'Needs improvement.',
        'compliance_status' => [],
        'summary_statistics' => [],
        'top_risks' => [],
        'issue_details' => [],
        'remediations' => [],
    ]);

    Ai::fakeAgent(AuditAgent::class, [json_decode($aiJson, true)]);

    (new GenerateAiAuditJob($this->audit))->handle(app(\App\Services\AiAuditService::class));

    expect($this->audit->fresh()->executive_summary)->toBe('Needs improvement.');
});

it('persists legal_precedents from the AI response', function (): void {
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

    Ai::fakeAgent(AuditAgent::class, [[
        'overall_score' => 55,
        'executive_summary' => 'Legal risk identified.',
        'compliance_status' => [],
        'summary_statistics' => [],
        'top_risks' => [],
        'issue_details' => [],
        'remediations' => [],
        'legal_precedents' => $precedents,
    ]]);

    (new GenerateAiAuditJob($this->audit))->handle(app(\App\Services\AiAuditService::class));

    $fresh = $this->audit->fresh();
    expect($fresh->legal_precedents)->toHaveCount(2)
        ->and($fresh->legal_precedents[0]['case_name'])->toBe('Gil v. Winn-Dixie Stores, Inc.')
        ->and($fresh->legal_precedents[1]['outcome'])->toBe('plaintiff_won');
});

it('defaults legal_precedents to empty array when AI omits it', function (): void {
    Ai::fakeAgent(AuditAgent::class, [[
        'overall_score' => 80,
        'executive_summary' => 'Clean audit.',
        'compliance_status' => [],
        'summary_statistics' => [],
        'top_risks' => [],
        'issue_details' => [],
        'remediations' => [],
    ]]);

    (new GenerateAiAuditJob($this->audit))->handle(app(\App\Services\AiAuditService::class));

    expect($this->audit->fresh()->legal_precedents)->toBe([]);
});

// ─── failure handling ─────────────────────────────────────────────────────────

it('sets Failed status and records error_message when the job fails', function (): void {
    $job = new GenerateAiAuditJob($this->audit);
    $job->failed(new \RuntimeException('OpenAI returned HTTP 500'));

    $fresh = $this->audit->fresh();
    expect($fresh->status)->toBe(AuditStatus::Failed)
        ->and($fresh->error_message)->toContain('OpenAI returned HTTP 500');
});

it('truncates long error messages to 250 characters', function (): void {
    $longMessage = str_repeat('x', 300);

    (new GenerateAiAuditJob($this->audit))->failed(new \RuntimeException($longMessage));

    expect(strlen($this->audit->fresh()->error_message))->toBe(250);
});

// ─── queue dispatch ───────────────────────────────────────────────────────────

it('can be pushed to the queue', function (): void {
    \Illuminate\Support\Facades\Queue::fake();

    GenerateAiAuditJob::dispatch($this->audit);

    \Illuminate\Support\Facades\Queue::assertPushed(
        GenerateAiAuditJob::class,
        fn ($job) => $job->audit->is($this->audit),
    );
});

it('implements ShouldBeUnique with the audit id as the unique key', function (): void {
    $job = new GenerateAiAuditJob($this->audit);

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $this->audit->id)
        ->and($job->uniqueFor)->toBe(300);
});
