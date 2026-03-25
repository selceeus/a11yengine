<?php

use App\Jobs\IndexRemediationPatternJob;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\RemediationEmbedding;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
});

// ─── rag:reindex-remediations ─────────────────────────────────────────────────

it('dispatches a job for each issue that has ai_suggestions but no embedding', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    Issue::factory()->for($this->agency)->count(3)->create([
        'ai_suggestions' => ['explanation' => 'test'],
    ]);

    $this->artisan('rag:reindex-remediations')->assertSuccessful();

    Queue::assertPushed(IndexRemediationPatternJob::class, 3);
});

it('skips issues that already have a RemediationEmbedding', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    $indexed = Issue::factory()->for($this->agency)->create(['ai_suggestions' => ['explanation' => 'test']]);
    RemediationEmbedding::factory()->create(['issue_id' => $indexed->id]);

    Issue::factory()->for($this->agency)->create(['ai_suggestions' => ['explanation' => 'test']]);

    $this->artisan('rag:reindex-remediations')->assertSuccessful();

    Queue::assertPushed(IndexRemediationPatternJob::class, 1);
});

it('skips issues with no ai_suggestions', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    Issue::factory()->for($this->agency)->count(2)->create(['ai_suggestions' => null]);

    $this->artisan('rag:reindex-remediations')->assertSuccessful();

    Queue::assertNotPushed(IndexRemediationPatternJob::class);
});

it('outputs a nothing-to-do message when all issues are indexed', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    $issue = Issue::factory()->for($this->agency)->create(['ai_suggestions' => ['explanation' => 'test']]);
    RemediationEmbedding::factory()->create(['issue_id' => $issue->id]);

    $this->artisan('rag:reindex-remediations')
        ->assertSuccessful()
        ->expectsOutputToContain('Nothing to do');
});

it('respects the --limit option', function (): void {
    Queue::fake([IndexRemediationPatternJob::class]);

    Issue::factory()->for($this->agency)->count(5)->create(['ai_suggestions' => ['explanation' => 'test']]);

    $this->artisan('rag:reindex-remediations --limit=2')->assertSuccessful();

    Queue::assertPushed(IndexRemediationPatternJob::class, 2);
});
