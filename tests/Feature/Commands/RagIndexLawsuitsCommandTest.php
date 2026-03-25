<?php

use App\Jobs\IngestLawsuitDataJob;
use App\Models\LawsuitEmbedding;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── rag:index-lawsuits ───────────────────────────────────────────────────────

it('dispatches an IngestLawsuitDataJob for every record in the dataset', function (): void {
    Queue::fake();

    $this->artisan('rag:index-lawsuits')->assertSuccessful();

    $path = database_path('data/lawsuits.json');
    $records = json_decode(file_get_contents($path), true);

    Queue::assertPushed(IngestLawsuitDataJob::class, count($records));
});

it('truncates lawsuit_embeddings when --fresh flag is passed', function (): void {
    Queue::fake();

    LawsuitEmbedding::factory()->create();

    $this->artisan('rag:index-lawsuits --fresh')->assertSuccessful();

    expect(LawsuitEmbedding::query()->count())->toBe(0);
});

it('returns success exit code', function (): void {
    Queue::fake();

    $this->artisan('rag:index-lawsuits')->assertSuccessful();
});

it('skips seeding when --skip-if-indexed and all records are already indexed', function (): void {
    $path = database_path('data/lawsuits.json');
    $records = json_decode(file_get_contents($path), true);

    LawsuitEmbedding::factory()->count(count($records))->create();

    $this->artisan('rag:index-lawsuits --skip-if-indexed')
        ->assertSuccessful()
        ->expectsOutputToContain('already fully indexed');
});

it('continues seeding when --skip-if-indexed but records are only partially indexed', function (): void {
    Queue::fake();

    LawsuitEmbedding::factory()->create();

    $this->artisan('rag:index-lawsuits --skip-if-indexed')->assertSuccessful();
});
