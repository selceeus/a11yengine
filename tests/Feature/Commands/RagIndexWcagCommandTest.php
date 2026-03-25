<?php

use App\Jobs\EmbedWcagDocumentJob;
use App\Models\WcagEmbedding;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── rag:index-wcag ───────────────────────────────────────────────────────────

it('dispatches an EmbedWcagDocumentJob for every chunk in the dataset', function (): void {
    Queue::fake();

    $this->artisan('rag:index-wcag')->assertSuccessful();

    $path = database_path('data/wcag_criteria.json');
    $criteria = json_decode(file_get_contents($path), true);
    $totalChunks = array_sum(array_map(fn (array $c) => count($c['chunks']), $criteria));

    Queue::assertPushed(EmbedWcagDocumentJob::class, $totalChunks);
});

it('dispatches jobs with correct chunk_index', function (): void {
    Queue::fake();

    $this->artisan('rag:index-wcag')->assertSuccessful();

    $dispatched = Queue::pushed(EmbedWcagDocumentJob::class);

    $firstCriterionChunks = $dispatched->filter(
        fn (EmbedWcagDocumentJob $job) => $job->chunk['criterion'] === '1.1.1'
    );

    $indices = $firstCriterionChunks->map(fn (EmbedWcagDocumentJob $job) => $job->chunk['chunk_index'])->sort()->values();

    expect($indices->first())->toBe(0);
});

it('truncates wcag_embeddings when --fresh flag is passed', function (): void {
    Queue::fake();

    WcagEmbedding::factory()->create();

    $this->artisan('rag:index-wcag --fresh')->assertSuccessful();

    expect(WcagEmbedding::query()->count())->toBe(0);
});
