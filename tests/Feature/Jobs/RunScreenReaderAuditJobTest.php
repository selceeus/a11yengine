<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Enums\ScanPageStatus;
use App\Jobs\RunScreenReaderAuditJob;
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
    ]);
});

// ─── Queue dispatching ────────────────────────────────────────────────────────

it('can be dispatched to the queue', function (): void {
    Queue::fake();

    RunScreenReaderAuditJob::dispatch($this->scan, 'https://example.com/', []);

    Queue::assertPushed(
        RunScreenReaderAuditJob::class,
        fn ($job) => $job->scan->is($this->scan) && $job->pageUrl === 'https://example.com/',
    );
});

// ─── Completion flag ──────────────────────────────────────────────────────────

it('marks screen_reader_completed on the ScanPage stub after handling', function (): void {
    $processor = Mockery::mock(ProcessHtmlScan::class);
    $processor->expects('handle')->once();

    (new RunScreenReaderAuditJob($this->scan, 'https://example.com/', [
        ['id' => 'sr-missing-h1', 'impact' => 'moderate', 'nodes' => [['target' => ['body'], 'failureSummary' => 'Add h1']]],
    ]))->handle($processor);

    expect($this->stub->refresh()->screen_reader_completed)->toBeTrue();
});

it('marks screen_reader_completed even when violations array is empty', function (): void {
    $processor = Mockery::mock(ProcessHtmlScan::class);
    $processor->expects('handle')->once();

    (new RunScreenReaderAuditJob($this->scan, 'https://example.com/', []))->handle($processor);

    expect($this->stub->refresh()->screen_reader_completed)->toBeTrue();
});

// ─── Soft-fail behaviour ──────────────────────────────────────────────────────

it('catches processor exceptions and still marks completion', function (): void {
    Log::spy();

    $processor = Mockery::mock(ProcessHtmlScan::class);
    $processor->expects('handle')->once()->andThrow(new RuntimeException('Processing error'));

    (new RunScreenReaderAuditJob($this->scan, 'https://example.com/', []))->handle($processor);

    expect($this->stub->refresh()->screen_reader_completed)->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});

it('marks screen_reader_completed on permanent failure', function (): void {
    Log::spy();

    (new RunScreenReaderAuditJob($this->scan, 'https://example.com/', []))
        ->failed(new RuntimeException('Fatal error'));

    expect($this->stub->refresh()->screen_reader_completed)->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});
