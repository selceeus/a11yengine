<?php

use App\Domain\Risk\AiRiskAdvisorService;
use App\Services\RagRetrievalService;

// ─── buildPrompt ────────────────────────────────────────────────────────────

it('includes issue count and property name in the risk advisory prompt', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
        $mock->shouldReceive('findLawsuits')->andReturn([]);
    });

    $issues = [
        ['id' => 1, 'rule_key' => 'image-alt', 'wcag_criteria' => '1.1.1 A', 'severity' => 'critical', 'occurrence_count' => 5, 'risk_weight' => 0.9, 'traffic_score' => 4.5],
    ];

    $prompt = app(AiRiskAdvisorService::class)->buildPrompt($issues, 45.0, 'Acme Corp');

    expect($prompt)
        ->toContain('1 open accessibility issues')
        ->toContain('Acme Corp')
        ->toContain('risk score of 45/100');
});

it('includes ADA legal precedents section when RAG returns lawsuits', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
        $mock->shouldReceive('findLawsuits')->andReturn([
            [
                'case_name' => 'Robles v. Domino\'s Pizza',
                'filed_year' => 2016,
                'industry' => 'food',
                'outcome' => 'plaintiff_won',
                'settlement_amount' => null,
                'summary' => 'Blind plaintiff sued over inaccessible website and app.',
                'score' => 0.88,
            ],
        ]);
    });

    $prompt = app(AiRiskAdvisorService::class)->buildPrompt([], null, 'Pizza Co', 'food');

    expect($prompt)
        ->toContain('## ADA Legal Precedents (Knowledge Base)')
        ->toContain('Robles v. Domino\'s Pizza')
        ->toContain('2016');
});

it('includes WCAG compliance context when RAG returns chunks', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([
            [
                'criterion' => '1.4.3',
                'level' => 'AA',
                'title' => 'Contrast (Minimum)',
                'chunk' => 'Text must have a contrast ratio of at least 4.5:1.',
                'score' => 0.9,
            ],
        ]);
        $mock->shouldReceive('findLawsuits')->andReturn([]);
    });

    $issues = [
        ['id' => 1, 'rule_key' => 'color-contrast', 'wcag_criteria' => '1.4.3 AA', 'severity' => 'serious', 'occurrence_count' => 3, 'risk_weight' => 0.7, 'traffic_score' => 2.1],
    ];

    $prompt = app(AiRiskAdvisorService::class)->buildPrompt($issues, null, 'Example Site', null);

    expect($prompt)
        ->toContain('## WCAG Compliance Context (Knowledge Base)')
        ->toContain('1.4.3 Contrast (Minimum)');
});

it('omits RAG sections when knowledge base is empty', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
        $mock->shouldReceive('findLawsuits')->andReturn([]);
    });

    $prompt = app(AiRiskAdvisorService::class)->buildPrompt([], null, 'Example Site');

    expect($prompt)
        ->not->toContain('## ADA Legal Precedents (Knowledge Base)')
        ->not->toContain('## WCAG Compliance Context (Knowledge Base)');
});
