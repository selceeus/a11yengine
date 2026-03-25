<?php

use App\Domain\Issues\AiRemediationService;
use App\Enums\IssueSeverity;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\RagRetrievalService;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);
});

function remediationBuildPrompt(AiRemediationService $service, Issue $issue): string
{
    $method = new ReflectionMethod($service, 'buildPrompt');

    return $method->invoke($service, $issue);
}

// ─── buildPrompt RAG injection ────────────────────────────────────────────────

it('includes issue details in the remediation prompt', function (): void {
    $mock = $this->mock(RagRetrievalService::class);
    $mock->shouldReceive('findWcagChunks')->andReturn([]);
    $mock->shouldReceive('findSimilarRemediations')->andReturn([]);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1 A',
        'severity' => IssueSeverity::Critical,
        'description' => 'Images must have alt text',
    ]);

    $prompt = remediationBuildPrompt(app(AiRemediationService::class), $issue);

    expect($prompt)
        ->toContain('image-alt')
        ->toContain('1.1.1 A')
        ->toContain('Images must have alt text');
});

it('includes WCAG guidance section when RAG returns chunks', function (): void {
    $mock = $this->mock(RagRetrievalService::class);
    $mock->shouldReceive('findWcagChunks')->andReturn([
        [
            'criterion' => '1.1.1',
            'level' => 'A',
            'title' => 'Non-text Content',
            'chunk' => 'All non-text content must have a text alternative.',
            'score' => 0.95,
        ],
    ]);
    $mock->shouldReceive('findSimilarRemediations')->andReturn([]);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1 A',
    ]);

    $prompt = remediationBuildPrompt(app(AiRemediationService::class), $issue);

    expect($prompt)
        ->toContain('## WCAG Guidance (Knowledge Base)')
        ->toContain('1.1.1 Non-text Content')
        ->toContain('All non-text content must have a text alternative.');
});

it('includes similar past remediations when RAG returns patterns', function (): void {
    $mock = $this->mock(RagRetrievalService::class);
    $mock->shouldReceive('findWcagChunks')->andReturn([]);
    $mock->shouldReceive('findSimilarRemediations')->andReturn([
        [
            'rule_key' => 'image-alt',
            'wcag_criteria' => '1.1.1',
            'description' => 'Missing alt text',
            'resolution' => 'Add descriptive alt attributes to all img elements.',
            'outcome' => 'resolved',
            'score' => 0.9,
        ],
    ]);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1 A',
    ]);

    $prompt = remediationBuildPrompt(app(AiRemediationService::class), $issue);

    expect($prompt)
        ->toContain('## Similar Past Remediations')
        ->toContain('`image-alt`')
        ->toContain('Add descriptive alt attributes to all img elements.');
});

it('omits RAG sections when knowledge base is empty', function (): void {
    $mock = $this->mock(RagRetrievalService::class);
    $mock->shouldReceive('findWcagChunks')->andReturn([]);
    $mock->shouldReceive('findSimilarRemediations')->andReturn([]);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $prompt = remediationBuildPrompt(app(AiRemediationService::class), $issue);

    expect($prompt)
        ->not->toContain('## WCAG Guidance (Knowledge Base)')
        ->not->toContain('## Similar Past Remediations');
});
