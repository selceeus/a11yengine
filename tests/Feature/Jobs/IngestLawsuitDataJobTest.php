<?php

use App\Jobs\IngestLawsuitDataJob;
use App\Models\LawsuitEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

$validRecord = fn (): array => [
    'case_name' => 'Robles v. Domino\'s Pizza LLC',
    'filed_year' => 2016,
    'industry' => 'retail',
    'violation_type' => 'screen reader incompatibility',
    'wcag_criteria' => ['1.1.1', '4.1.2'],
    'outcome' => 'plaintiff_won',
    'settlement_amount' => null,
    'summary' => 'Ninth Circuit ruled Domino\'s website must comply with ADA.',
];

// ─── happy path ───────────────────────────────────────────────────────────────

it('creates a LawsuitEmbedding record from a lawsuit array', function () use ($validRecord): void {
    $embedding = array_fill(0, 1536, 0.1);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => $embedding]],
        ]),
    ]);

    (new IngestLawsuitDataJob($validRecord()))->handle(app(EmbeddingService::class));

    $record = LawsuitEmbedding::query()->first();

    expect($record)->not->toBeNull()
        ->and($record->case_name)->toBe("Robles v. Domino's Pizza LLC")
        ->and($record->filed_year)->toBe(2016)
        ->and($record->industry)->toBe('retail')
        ->and($record->outcome)->toBe('plaintiff_won')
        ->and($record->embedding)->toBe($embedding);
});

it('upserts on re-dispatch for the same case_name', function () use ($validRecord): void {
    $embedding1 = array_fill(0, 1536, 0.1);
    $embedding2 = array_fill(0, 1536, 0.2);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::sequence()
            ->push(['data' => [['embedding' => $embedding1]]])
            ->push(['data' => [['embedding' => $embedding2]]]),
    ]);

    $service = app(EmbeddingService::class);
    (new IngestLawsuitDataJob($validRecord()))->handle($service);
    (new IngestLawsuitDataJob($validRecord()))->handle($service);

    expect(LawsuitEmbedding::query()->count())->toBe(1)
        ->and(LawsuitEmbedding::query()->first()->embedding)->toBe($embedding2);
});

it('stores null settlement_amount when not provided', function () use ($validRecord): void {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.0)]],
        ]),
    ]);

    (new IngestLawsuitDataJob($validRecord()))->handle(app(EmbeddingService::class));

    expect(LawsuitEmbedding::query()->first()->settlement_amount)->toBeNull();
});

it('stores wcag_criteria as an array', function () use ($validRecord): void {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.0)]],
        ]),
    ]);

    (new IngestLawsuitDataJob($validRecord()))->handle(app(EmbeddingService::class));

    expect(LawsuitEmbedding::query()->first()->wcag_criteria)->toBe(['1.1.1', '4.1.2']);
});

it('stores metadata with source and indexed_at', function () use ($validRecord): void {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.0)]],
        ]),
    ]);

    (new IngestLawsuitDataJob($validRecord()))->handle(app(EmbeddingService::class));

    $metadata = LawsuitEmbedding::query()->first()->metadata;

    expect($metadata)->toHaveKey('source', 'lawsuits.json')
        ->and($metadata)->toHaveKey('indexed_at');
});

// ─── failed ───────────────────────────────────────────────────────────────────

it('logs an error and does not throw when failed() is called', function () use ($validRecord): void {
    Log::spy();

    $job = new IngestLawsuitDataJob($validRecord());
    $job->failed(new RuntimeException('API timeout'));

    Log::shouldHaveReceived('error')->once();
});
