<?php

use App\Jobs\IndexRemediationPatternJob;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\RemediationEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
});

// ─── happy path ───────────────────────────────────────────────────────────────

it('creates a RemediationEmbedding record from an issue with ai_suggestions', function (): void {
    $embedding = array_fill(0, 1536, 0.1);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => $embedding]],
        ]),
    ]);

    $issue = Issue::factory()->for($this->agency)->create([
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'ai_suggestions' => [
            'explanation' => 'Image is missing alt text.',
            'code_fix' => '<img src="example.jpg" alt="Example description">',
            'aria_fix' => null,
            'remediation_steps' => ['Add alt attribute to the img element.'],
            'severity_rating' => 'high',
            'wcag_level' => 'A',
            'estimated_effort' => '15 minutes',
        ],
    ]);

    (new IndexRemediationPatternJob($issue))->handle(app(EmbeddingService::class));

    $record = RemediationEmbedding::query()->first();

    expect($record)->not->toBeNull()
        ->and($record->issue_id)->toBe($issue->id)
        ->and($record->rule_key)->toBe('image-alt')
        ->and($record->wcag_criteria)->toBe('1.1.1')
        ->and($record->embedding)->toBe($embedding);
});

it('upserts on re-dispatch for the same issue', function (): void {
    $embedding1 = array_fill(0, 1536, 0.1);
    $embedding2 = array_fill(0, 1536, 0.2);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::sequence()
            ->push(['data' => [['embedding' => $embedding1]]])
            ->push(['data' => [['embedding' => $embedding2]]]),
    ]);

    $issue = Issue::factory()->for($this->agency)->create([
        'ai_suggestions' => [
            'explanation' => 'Image is missing alt text.',
            'code_fix' => '<img alt="x">',
            'aria_fix' => null,
            'remediation_steps' => [],
            'severity_rating' => 'high',
        ],
    ]);

    $service = app(EmbeddingService::class);
    (new IndexRemediationPatternJob($issue))->handle($service);
    (new IndexRemediationPatternJob($issue))->handle($service);

    expect(RemediationEmbedding::query()->count())->toBe(1)
        ->and(RemediationEmbedding::query()->first()->embedding)->toBe($embedding2);
});

it('skips processing when ai_suggestions is null', function (): void {
    Http::fake();

    $issue = Issue::factory()->for($this->agency)->create(['ai_suggestions' => null]);

    (new IndexRemediationPatternJob($issue))->handle(app(EmbeddingService::class));

    expect(RemediationEmbedding::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('skips processing when ai_suggestions is empty array', function (): void {
    Http::fake();

    $issue = Issue::factory()->for($this->agency)->create(['ai_suggestions' => []]);

    (new IndexRemediationPatternJob($issue))->handle(app(EmbeddingService::class));

    expect(RemediationEmbedding::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('stores metadata with wcag_category and estimated_effort', function (): void {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.0)]],
        ]),
    ]);

    $issue = Issue::factory()->for($this->agency)->create([
        'wcag_category' => 'Perceivable',
        'ai_suggestions' => [
            'explanation' => 'Missing alt text.',
            'code_fix' => '<img alt="x">',
            'aria_fix' => null,
            'remediation_steps' => [],
            'severity_rating' => 'high',
            'wcag_level' => 'A',
            'estimated_effort' => '15 minutes',
        ],
    ]);

    (new IndexRemediationPatternJob($issue))->handle(app(EmbeddingService::class));

    $metadata = RemediationEmbedding::query()->first()->metadata;

    expect($metadata)->toHaveKey('wcag_category', 'Perceivable')
        ->and($metadata)->toHaveKey('estimated_effort', '15 minutes')
        ->and($metadata)->toHaveKey('indexed_at');
});

// ─── failed ───────────────────────────────────────────────────────────────────

it('logs an error and does not throw when failed() is called', function (): void {
    Log::spy();

    $issue = Issue::factory()->for($this->agency)->create(['ai_suggestions' => null]);
    $job = new IndexRemediationPatternJob($issue);
    $job->failed(new RuntimeException('Queue failure'));

    Log::shouldHaveReceived('error')->once();
});
