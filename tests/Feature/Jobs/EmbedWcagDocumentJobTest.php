<?php

use App\Jobs\EmbedWcagDocumentJob;
use App\Models\WcagEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

$validChunk = fn (): array => [
    'criterion' => '1.1.1',
    'chunk_index' => 0,
    'level' => 'A',
    'title' => 'Non-text Content',
    'chunk' => 'All non-text content must have a text alternative.',
];

// ─── happy path ───────────────────────────────────────────────────────────────

it('creates a WcagEmbedding record with the returned embedding', function () use ($validChunk): void {
    $embedding = array_fill(0, 1536, 0.1);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => $embedding]],
        ]),
    ]);

    (new EmbedWcagDocumentJob($validChunk()))->handle(app(EmbeddingService::class));

    $record = WcagEmbedding::query()->first();

    expect($record)->not->toBeNull()
        ->and($record->criterion)->toBe('1.1.1')
        ->and($record->chunk_index)->toBe(0)
        ->and($record->level)->toBe('A')
        ->and($record->title)->toBe('Non-text Content')
        ->and($record->embedding)->toBe($embedding);
});

it('upserts on re-dispatch for the same criterion and chunk_index', function () use ($validChunk): void {
    $embedding1 = array_fill(0, 1536, 0.1);
    $embedding2 = array_fill(0, 1536, 0.2);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::sequence()
            ->push(['data' => [['embedding' => $embedding1]]])
            ->push(['data' => [['embedding' => $embedding2]]]),
    ]);

    $service = app(EmbeddingService::class);
    (new EmbedWcagDocumentJob($validChunk()))->handle($service);
    (new EmbedWcagDocumentJob($validChunk()))->handle($service);

    expect(WcagEmbedding::query()->count())->toBe(1)
        ->and(WcagEmbedding::query()->first()->embedding)->toBe($embedding2);
});

it('stores chunk_index to differentiate multiple chunks for the same criterion', function () use ($validChunk): void {
    $embedding = array_fill(0, 1536, 0.1);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => $embedding]],
        ]),
    ]);

    $service = app(EmbeddingService::class);

    $chunk0 = $validChunk();
    $chunk1 = array_merge($validChunk(), ['chunk_index' => 1, 'chunk' => 'Second chunk text.']);

    (new EmbedWcagDocumentJob($chunk0))->handle($service);
    (new EmbedWcagDocumentJob($chunk1))->handle($service);

    expect(WcagEmbedding::query()->count())->toBe(2);
});

it('stores metadata with source and indexed_at', function () use ($validChunk): void {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.0)]],
        ]),
    ]);

    (new EmbedWcagDocumentJob($validChunk()))->handle(app(EmbeddingService::class));

    $metadata = WcagEmbedding::query()->first()->metadata;

    expect($metadata)->toHaveKey('source', 'wcag_criteria.json')
        ->and($metadata)->toHaveKey('indexed_at');
});

// ─── failed ───────────────────────────────────────────────────────────────────

it('logs an error and does not throw when failed() is called', function () use ($validChunk): void {
    Log::spy();

    $job = new EmbedWcagDocumentJob($validChunk());
    $job->failed(new RuntimeException('API down'));

    Log::shouldHaveReceived('error')->once();
});
