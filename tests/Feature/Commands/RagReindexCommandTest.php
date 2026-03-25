<?php

use App\Jobs\EmbedWcagDocumentJob;
use App\Jobs\IndexRemediationPatternJob;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── rag:reindex ──────────────────────────────────────────────────────────────

it('returns failure for an unknown store', function (): void {
    $this->artisan('rag:reindex --only=unknown')
        ->assertFailed()
        ->expectsOutputToContain('Unknown store(s): unknown');
});

it('returns failure when one of multiple stores is unknown', function (): void {
    $this->artisan('rag:reindex --only=wcag,bogus')
        ->assertFailed()
        ->expectsOutputToContain('Unknown store(s): bogus');
});

it('runs only the remediations store when --only=remediations', function (): void {
    Queue::fake([IndexRemediationPatternJob::class, EmbedWcagDocumentJob::class]);

    $this->artisan('rag:reindex --only=remediations')->assertSuccessful();

    Queue::assertNotPushed(EmbedWcagDocumentJob::class);
});

it('runs the wcag store when --only=wcag', function (): void {
    Queue::fake([EmbedWcagDocumentJob::class]);

    $this->artisan('rag:reindex --only=wcag')->assertSuccessful();

    Queue::assertPushed(EmbedWcagDocumentJob::class);
});
