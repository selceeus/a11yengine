<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Enums\ScanPageStatus;
use App\Jobs\RunInteractiveAuditJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->stub = ScanPage::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'url' => 'https://example.com/',
        'violations_count' => 0,
        'status' => ScanPageStatus::Pending,
        'axe_completed' => false,
        'lighthouse_completed' => false,
        'screen_reader_completed' => false,
        'content_completed' => false,
        'keyboard_completed' => false,
        'interactive_completed' => false,
    ]);
});

// ─── Queue dispatching ────────────────────────────────────────────────────────

it('can be dispatched to the queue', function (): void {
    Queue::fake();

    RunInteractiveAuditJob::dispatch($this->scan, 'https://example.com/', []);

    Queue::assertPushed(
        RunInteractiveAuditJob::class,
        fn ($job) => $job->scan->is($this->scan) && $job->pageUrl === 'https://example.com/',
    );
});

// ─── Completion flag ──────────────────────────────────────────────────────────

it('marks interactive_completed on the ScanPage stub after handling', function (): void {
    $processor = Mockery::mock(ProcessHtmlScan::class);
    $processor->expects('handle')->once();

    (new RunInteractiveAuditJob($this->scan, 'https://example.com/', [
        ['id' => 'int-focus-trap', 'impact' => 'critical', 'nodes' => [['target' => ['#modal'], 'failureSummary' => 'Focus is trapped']]],
    ]))->handle($processor);

    expect($this->stub->refresh()->interactive_completed)->toBeTrue();
});

it('marks interactive_completed even when violations array is empty', function (): void {
    $processor = Mockery::mock(ProcessHtmlScan::class);
    $processor->expects('handle')->once();

    (new RunInteractiveAuditJob($this->scan, 'https://example.com/', []))->handle($processor);

    expect($this->stub->refresh()->interactive_completed)->toBeTrue();
});

// ─── Soft-fail behaviour ──────────────────────────────────────────────────────

it('catches processor exceptions and still marks completion', function (): void {
    Log::spy();

    $processor = Mockery::mock(ProcessHtmlScan::class);
    $processor->expects('handle')->once()->andThrow(new RuntimeException('Processing error'));

    (new RunInteractiveAuditJob($this->scan, 'https://example.com/', []))->handle($processor);

    expect($this->stub->refresh()->interactive_completed)->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});

it('marks interactive_completed on permanent failure', function (): void {
    Log::spy();

    (new RunInteractiveAuditJob($this->scan, 'https://example.com/', []))
        ->failed(new RuntimeException('Fatal error'));

    expect($this->stub->refresh()->interactive_completed)->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});
