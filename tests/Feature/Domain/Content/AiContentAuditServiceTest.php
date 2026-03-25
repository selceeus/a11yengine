<?php

use App\Domain\Content\AiContentAuditService;
use App\Services\RagRetrievalService;

// ─── buildPrompt ────────────────────────────────────────────────────────────

it('includes page count and property name in the content audit prompt', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
    });

    $pages = [
        ['url' => 'https://example.com/', 'html_snippet' => null, 'findings' => []],
        ['url' => 'https://example.com/about', 'html_snippet' => null, 'findings' => []],
    ];

    $prompt = app(AiContentAuditService::class)->buildPrompt($pages, 'Example Site');

    expect($prompt)
        ->toContain('2 page(s)')
        ->toContain('Example Site');
});

it('includes WCAG content guidance section when RAG returns chunks', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([
            [
                'criterion' => '1.1.1',
                'level' => 'A',
                'title' => 'Non-text Content',
                'chunk' => 'Images must have descriptive alt text for screen reader users.',
                'score' => 0.95,
            ],
            [
                'criterion' => '2.4.4',
                'level' => 'A',
                'title' => 'Link Purpose (In Context)',
                'chunk' => 'Link text must describe the link destination.',
                'score' => 0.88,
            ],
        ]);
    });

    $prompt = app(AiContentAuditService::class)->buildPrompt([], 'Acme');

    expect($prompt)
        ->toContain('## WCAG Content Accessibility Guidance (Knowledge Base)')
        ->toContain('1.1.1 Non-text Content')
        ->toContain('2.4.4 Link Purpose (In Context)');
});

it('omits WCAG section when knowledge base returns no chunks', function (): void {
    $this->mock(RagRetrievalService::class, function ($mock): void {
        $mock->shouldReceive('findWcagChunks')->andReturn([]);
    });

    $prompt = app(AiContentAuditService::class)->buildPrompt([], 'Acme');

    expect($prompt)->not->toContain('## WCAG Content Accessibility Guidance (Knowledge Base)');
});
