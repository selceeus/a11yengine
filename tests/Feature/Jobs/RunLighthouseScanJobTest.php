<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Domain\Scans\Scan as ScanDomain;
use App\Exceptions\ScanProcessException;
use App\Jobs\RunLighthouseScanJob;
use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\LighthouseResult;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use App\Services\CrawlerRunner;
use App\Services\LighthouseRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['base_url' => 'https://example.com']);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();
});

// ─── Queue dispatching ────────────────────────────────────────────────────────

it('can be dispatched to the queue', function (): void {
    Queue::fake();

    RunLighthouseScanJob::dispatch($this->scan, 'https://example.com/');

    Queue::assertPushed(
        RunLighthouseScanJob::class,
        fn ($job) => $job->scan->is($this->scan) && $job->pageUrl === 'https://example.com/',
    );
});

// ─── Result persistence ───────────────────────────────────────────────────────

it('persists a LighthouseResult when the runner succeeds', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andReturn([
        'url' => 'https://example.com/',
        'performance_score' => 92,
        'accessibility_score' => 78,
        'best_practices_score' => 85,
        'seo_score' => 95,
        'first_contentful_paint' => 1234.5,
        'largest_contentful_paint' => 2500.0,
        'total_blocking_time' => 150.0,
        'cumulative_layout_shift' => 0.05,
        'raw_metrics' => [],
    ]);

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    expect(LighthouseResult::withoutGlobalScopes()->count())->toBe(1);
});

it('stores correct scores in the database', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andReturn([
        'url' => 'https://example.com/',
        'performance_score' => 92,
        'accessibility_score' => 78,
        'best_practices_score' => 85,
        'seo_score' => 95,
        'first_contentful_paint' => 1234.5,
        'largest_contentful_paint' => 2500.0,
        'total_blocking_time' => 150.0,
        'cumulative_layout_shift' => 0.05,
        'raw_metrics' => [],
    ]);

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    $result = LighthouseResult::withoutGlobalScopes()->first();
    expect($result->performance_score)->toBe(92)
        ->and($result->accessibility_score)->toBe(78)
        ->and($result->best_practices_score)->toBe(85)
        ->and($result->seo_score)->toBe(95);
});

it('stores correct metrics in the database', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andReturn([
        'url' => 'https://example.com/',
        'performance_score' => 92,
        'accessibility_score' => 78,
        'best_practices_score' => 85,
        'seo_score' => 95,
        'first_contentful_paint' => 1234.5,
        'largest_contentful_paint' => 2500.0,
        'total_blocking_time' => 150.0,
        'cumulative_layout_shift' => 0.05,
        'raw_metrics' => ['fcp' => ['numericValue' => 1234.5]],
    ]);

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    $result = LighthouseResult::withoutGlobalScopes()->first();
    expect($result->first_contentful_paint)->toBe(1234.5)
        ->and($result->largest_contentful_paint)->toBe(2500.0)
        ->and($result->total_blocking_time)->toBe(150.0)
        ->and($result->cumulative_layout_shift)->toBe(0.05)
        ->and($result->raw_metrics)->toBeArray();
});

it('links the result to the correct agency and scan', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andReturn([
        'url' => 'https://example.com/',
        'performance_score' => 80,
        'accessibility_score' => 80,
        'best_practices_score' => 80,
        'seo_score' => 80,
        'first_contentful_paint' => 1000.0,
        'largest_contentful_paint' => 2000.0,
        'total_blocking_time' => 100.0,
        'cumulative_layout_shift' => 0.01,
        'raw_metrics' => [],
    ]);

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    $result = LighthouseResult::withoutGlobalScopes()->first();
    expect($result->agency_id)->toBe($this->agency->id)
        ->and($result->scan_id)->toBe($this->scan->id);
});

// ─── Soft failure ────────────────────────────────────────────────────────────

it('does not persist a result when the runner throws ScanProcessException', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andThrow(new ScanProcessException('Chrome unavailable'));

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    expect(LighthouseResult::withoutGlobalScopes()->count())->toBe(0);
});

it('logs a warning when the runner fails instead of rethrowing', function (): void {
    Log::spy();

    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andThrow(new ScanProcessException('Chrome unavailable'));

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    Log::shouldHaveReceived('warning')->once()->with('Lighthouse scan failed', Mockery::type('array'));
});

it('does not throw when the runner fails', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andThrow(new ScanProcessException('Chrome unavailable'));

    expect(fn () => (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner))
        ->not->toThrow(Exception::class);
});

// ─── RunScanJob ceiling dispatch ──────────────────────────────────────────────

it('dispatches at most max_pages Lighthouse jobs from RunScanJob', function (): void {
    Queue::fake();

    config(['lighthouse.max_pages' => 2]);

    Process::fake(['*' => Process::result(json_encode([
        ['url' => 'https://example.com/', 'violations' => []],
        ['url' => 'https://example.com/about', 'violations' => []],
        ['url' => 'https://example.com/contact', 'violations' => []],
    ]))]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ProcessHtmlScan::class), app(CrawlerRunner::class));

    Queue::assertPushed(RunLighthouseScanJob::class, 2);
});

it('dispatches no Lighthouse jobs when max_pages is 0', function (): void {
    Queue::fake();

    config(['lighthouse.max_pages' => 0]);

    Process::fake(['*' => Process::result(json_encode([
        ['url' => 'https://example.com/', 'violations' => []],
    ]))]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ProcessHtmlScan::class), app(CrawlerRunner::class));

    Queue::assertNothingPushed();
});

it('dispatches one Lighthouse job per page up to the ceiling', function (): void {
    Queue::fake();

    config(['lighthouse.max_pages' => 3]);

    Process::fake(['*' => Process::result(json_encode([
        ['url' => 'https://example.com/', 'violations' => []],
        ['url' => 'https://example.com/about', 'violations' => []],
    ]))]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ProcessHtmlScan::class), app(CrawlerRunner::class));

    // Only 2 pages crawled — ceiling of 3 does not inflate the count
    Queue::assertPushed(RunLighthouseScanJob::class, 2);
});
