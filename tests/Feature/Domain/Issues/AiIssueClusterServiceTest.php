<?php

use App\Domain\Issues\AiIssueClusterService;
use App\Services\RagRetrievalService;

// ─── buildPrompt ────────────────────────────────────────────────────────────

it('includes issue count and property name in the cluster prompt', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
        $mock->shouldReceive('findSimilarRemediations')->andReturn([]);
    });

    $issues = [
        ['id' => 1, 'rule_key' => 'image-alt', 'wcag_criteria' => '1.1.1 A', 'severity' => 'critical'],
        ['id' => 2, 'rule_key' => 'color-contrast', 'wcag_criteria' => '1.4.3 AA', 'severity' => 'serious'],
    ];

    $prompt = app(AiIssueClusterService::class)->buildPrompt($issues, 'Test Property');

    expect($prompt)
        ->toContain('2 open accessibility issues')
        ->toContain('Test Property');
});

it('includes WCAG guidance section when RAG returns chunks', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([
            [
                'criterion' => '1.1.1',
                'level' => 'A',
                'title' => 'Non-text Content',
                'chunk' => 'Images must have descriptive alt text.',
                'score' => 0.92,
            ],
        ]);
        $mock->shouldReceive('findSimilarRemediations')->andReturn([]);
    });

    $issues = [
        ['id' => 1, 'rule_key' => 'image-alt', 'wcag_criteria' => '1.1.1 A', 'severity' => 'critical'],
    ];

    $prompt = app(AiIssueClusterService::class)->buildPrompt($issues, 'Acme');

    expect($prompt)
        ->toContain('## WCAG Guidance (Knowledge Base)')
        ->toContain('1.1.1 Non-text Content');
});

it('includes similar remediations section when RAG returns patterns', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
        $mock->shouldReceive('findSimilarRemediations')->andReturn([
            [
                'rule_key' => 'image-alt',
                'wcag_criteria' => '1.1.1',
                'description' => 'Missing alt on product images',
                'resolution' => 'Add alt attributes to all product images using the product name.',
                'outcome' => 'resolved',
                'score' => 0.87,
            ],
        ]);
    });

    $issues = [
        ['id' => 1, 'rule_key' => 'image-alt', 'wcag_criteria' => '1.1.1 A', 'severity' => 'critical'],
    ];

    $prompt = app(AiIssueClusterService::class)->buildPrompt($issues, 'Acme');

    expect($prompt)
        ->toContain('## Similar Past Remediations (Knowledge Base)')
        ->toContain('Add alt attributes to all product images');
});

it('omits RAG sections when knowledge base is empty', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
        $mock->shouldReceive('findSimilarRemediations')->andReturn([]);
    });

    $prompt = app(AiIssueClusterService::class)->buildPrompt([], 'Acme');

    expect($prompt)
        ->not->toContain('## WCAG Guidance (Knowledge Base)')
        ->not->toContain('## Similar Past Remediations (Knowledge Base)');
});
