<?php

use App\Models\LawsuitEmbedding;
use App\Models\RemediationEmbedding;
use App\Models\WcagEmbedding;
use App\Services\EmbeddingService;
use App\Services\RagRetrievalService;

beforeEach(function (): void {
    $this->mockEmbedding = Mockery::mock(EmbeddingService::class);
    $this->mockEmbedding->allows('cosineSimilarity')->andReturnUsing(
        function (array $a, array $b): float {
            return array_sum(array_map(fn ($x, $y) => $x * $y, $a, $b));
        }
    );
    $this->app->instance(EmbeddingService::class, $this->mockEmbedding);
    $this->service = app(RagRetrievalService::class);
});

// ─── findWcagChunks ───────────────────────────────────────────────────────────

it('returns empty array when no wcag embeddings exist', function (): void {
    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    expect($this->service->findWcagChunks('color contrast'))->toBe([]);
});

it('returns ranked wcag chunks by cosine similarity', function (): void {
    WcagEmbedding::create([
        'criterion' => '1.4.3',
        'level' => 'AA',
        'title' => 'Contrast (Minimum)',
        'chunk' => 'Text must have 4.5:1 contrast ratio.',
        'embedding' => [1.0, 0.0],
    ]);

    WcagEmbedding::create([
        'criterion' => '1.1.1',
        'level' => 'A',
        'title' => 'Non-text Content',
        'chunk' => 'All non-text content must have a text alternative.',
        'embedding' => [0.0, 1.0],
    ]);

    $this->mockEmbedding->allows('embed')->andReturn([0.9, 0.1]);

    $results = $this->service->findWcagChunks('contrast', 2);

    expect($results)->toHaveCount(2)
        ->and($results[0]['criterion'])->toBe('1.4.3')
        ->and($results[0])->toHaveKey('score');
});

it('filters wcag chunks by criteria', function (): void {
    WcagEmbedding::create(['criterion' => '1.4.3', 'level' => 'AA', 'title' => 'Contrast', 'chunk' => 'text', 'embedding' => [1.0, 0.0]]);
    WcagEmbedding::create(['criterion' => '1.1.1', 'level' => 'A', 'title' => 'Images', 'chunk' => 'text', 'embedding' => [0.0, 1.0]]);

    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    $results = $this->service->findWcagChunks('contrast', 5, ['1.4.3']);

    expect($results)->toHaveCount(1)
        ->and($results[0]['criterion'])->toBe('1.4.3');
});

// ─── findLawsuits ────────────────────────────────────────────────────────────

it('returns empty array when no lawsuit embeddings exist', function (): void {
    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    expect($this->service->findLawsuits('image alt text'))->toBe([]);
});

it('returns ranked lawsuits by cosine similarity', function (): void {
    LawsuitEmbedding::create([
        'case_name' => 'Robles v. Dominos',
        'filed_year' => 2016,
        'industry' => 'retail',
        'violation_type' => 'screen reader incompatibility',
        'outcome' => 'plaintiff_won',
        'settlement_amount' => null,
        'summary' => 'Website inaccessible to blind users.',
        'embedding' => [1.0, 0.0],
    ]);

    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    $results = $this->service->findLawsuits('screen reader');

    expect($results)->toHaveCount(1)
        ->and($results[0]['case_name'])->toBe('Robles v. Dominos')
        ->and($results[0])->toHaveKey('score');
});

it('filters lawsuits by industry', function (): void {
    LawsuitEmbedding::create(['case_name' => 'Case A', 'filed_year' => 2020, 'industry' => 'retail', 'violation_type' => 'images', 'outcome' => 'settled', 'summary' => 'retail case', 'embedding' => [1.0, 0.0]]);
    LawsuitEmbedding::create(['case_name' => 'Case B', 'filed_year' => 2021, 'industry' => 'healthcare', 'violation_type' => 'forms', 'outcome' => 'settled', 'summary' => 'healthcare case', 'embedding' => [1.0, 0.0]]);

    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    $results = $this->service->findLawsuits('accessibility', 5, ['retail']);

    expect($results)->toHaveCount(1)
        ->and($results[0]['case_name'])->toBe('Case A');
});

// ─── findSimilarRemediations ─────────────────────────────────────────────────

it('returns empty array when no remediation embeddings exist', function (): void {
    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    expect($this->service->findSimilarRemediations('missing alt text'))->toBe([]);
});

it('returns ranked remediations by cosine similarity', function (): void {
    RemediationEmbedding::create([
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'description' => 'Image missing alt attribute',
        'resolution' => 'Add descriptive alt text to all img elements.',
        'outcome' => 'resolved',
        'embedding' => [1.0, 0.0],
    ]);

    $this->mockEmbedding->allows('embed')->andReturn([0.9, 0.1]);

    $results = $this->service->findSimilarRemediations('image alt text');

    expect($results)->toHaveCount(1)
        ->and($results[0]['rule_key'])->toBe('image-alt')
        ->and($results[0]['resolution'])->toContain('alt text')
        ->and($results[0])->toHaveKey('score');
});

it('limits results to the requested count', function (): void {
    for ($i = 1; $i <= 5; $i++) {
        RemediationEmbedding::create([
            'rule_key' => "rule-{$i}",
            'description' => "Description {$i}",
            'resolution' => "Resolution {$i}",
            'embedding' => [1.0, 0.0],
        ]);
    }

    $this->mockEmbedding->allows('embed')->andReturn([1.0, 0.0]);

    $results = $this->service->findSimilarRemediations('query', 3);

    expect($results)->toHaveCount(3);
});
