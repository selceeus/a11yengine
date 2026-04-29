<?php

use App\Ai\Agents\RemediationAgent;
use App\Domain\Issues\AiRemediationService;
use App\Jobs\GenerateIssueRemediationJob;
use App\Jobs\IndexRemediationPatternJob;
use App\Models\Agency;
use App\Models\Issue;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
});

// ─── auto-dispatch IndexRemediationPatternJob ─────────────────────────────────

it('dispatches IndexRemediationPatternJob after saving ai_suggestions', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    $aiSuggestions = [
        'explanation' => 'Image is missing alt text.',
        'wcag_reference' => '1.1.1',
        'wcag_level' => 'A',
        'user_impact' => 'serious',
        'severity_rating' => 'high',
        'code_fix' => '<img src="example.jpg" alt="Example">',
        'aria_fix' => null,
        'remediation_steps' => ['Add alt attribute.'],
        'testing_guidance' => 'Use a screen reader.',
        'estimated_effort' => 'low',
        'resources' => [],
    ];

    Ai::fakeAgent(RemediationAgent::class, [$aiSuggestions]);

    $issue = Issue::factory()->for($this->agency)->create([
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
    ]);

    (new GenerateIssueRemediationJob($issue))->handle(app(AiRemediationService::class));

    Queue::assertPushed(IndexRemediationPatternJob::class, function (IndexRemediationPatternJob $job) use ($issue): bool {
        return $job->issue->id === $issue->id;
    });
});

it('marks the issue as completed before dispatching the pattern job', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    $aiSuggestions = [
        'explanation' => 'Missing label.',
        'wcag_reference' => '3.3.2',
        'wcag_level' => 'A',
        'user_impact' => 'moderate',
        'severity_rating' => 'moderate',
        'code_fix' => null,
        'aria_fix' => null,
        'remediation_steps' => ['Add label element.'],
        'testing_guidance' => 'Check with keyboard navigation.',
        'estimated_effort' => 'low',
        'resources' => [],
    ];

    Ai::fakeAgent(RemediationAgent::class, [$aiSuggestions]);

    $issue = Issue::factory()->for($this->agency)->create(['rule_key' => 'label']);

    (new GenerateIssueRemediationJob($issue))->handle(app(AiRemediationService::class));

    expect($issue->fresh()->ai_remediation_status)->toBe('completed');
    Queue::assertPushed(IndexRemediationPatternJob::class);
});

it('implements ShouldBeUnique with the issue id as the unique key', function (): void {
    $issue = \App\Models\Issue::factory()->create(['agency_id' => $this->agency->id]);
    $job = new GenerateIssueRemediationJob($issue);

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $issue->id)
        ->and($job->uniqueFor)->toBe(120);
});
