<?php

use App\Services\EmbeddingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// ─── cosineSimilarity ─────────────────────────────────────────────────────────

it('returns 1.0 for identical vectors', function (): void {
    $service = app(EmbeddingService::class);
    $vec = [1.0, 0.0, 0.0];

    expect(round($service->cosineSimilarity($vec, $vec), 5))->toBe(1.0);
});

it('returns 0.0 for orthogonal vectors', function (): void {
    $service = app(EmbeddingService::class);

    expect(round($service->cosineSimilarity([1.0, 0.0], [0.0, 1.0]), 5))->toBe(0.0);
});

it('returns -1.0 for opposite vectors', function (): void {
    $service = app(EmbeddingService::class);

    expect(round($service->cosineSimilarity([1.0, 0.0], [-1.0, 0.0]), 5))->toBe(-1.0);
});

it('returns 0.0 for mismatched dimension vectors', function (): void {
    $service = app(EmbeddingService::class);

    expect($service->cosineSimilarity([1.0], [1.0, 0.0]))->toBe(0.0);
});

it('returns 0.0 for empty vectors', function (): void {
    $service = app(EmbeddingService::class);

    expect($service->cosineSimilarity([], []))->toBe(0.0);
});

// ─── embed ────────────────────────────────────────────────────────────────────

it('calls the OpenAI embeddings API and returns the vector', function (): void {
    $expected = array_fill(0, 1536, 0.1);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => $expected]],
        ]),
    ]);

    $service = app(EmbeddingService::class);
    $result = $service->embed('test query');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/embeddings')
        && $r->data()['model'] === 'text-embedding-3-small'
        && $r->data()['input'] === 'test query');

    expect($result)->toBe($expected);
});
