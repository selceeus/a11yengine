<?php

use App\Models\LawsuitEmbedding;
use App\Models\RemediationEmbedding;
use App\Models\WcagEmbedding;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── rag:status ───────────────────────────────────────────────────────────────

it('exits successfully with no embeddings indexed', function (): void {
    $this->artisan('rag:status')->assertSuccessful();
});

it('displays record counts for all three embedding stores', function (): void {
    WcagEmbedding::factory()->count(5)->create();
    LawsuitEmbedding::factory()->count(3)->create();

    $this->artisan('rag:status')
        ->assertSuccessful()
        ->expectsOutputToContain('5')
        ->expectsOutputToContain('3');
});

it('shows a hint to run indexing commands when no embeddings exist', function (): void {
    $this->artisan('rag:status')
        ->assertSuccessful()
        ->expectsOutputToContain('rag:index-wcag');
});

it('does not show hint when embeddings are indexed', function (): void {
    WcagEmbedding::factory()->count(1)->create();
    LawsuitEmbedding::factory()->count(1)->create();
    RemediationEmbedding::factory()->count(1)->create();

    $this->artisan('rag:status')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('rag:index-wcag');
});
