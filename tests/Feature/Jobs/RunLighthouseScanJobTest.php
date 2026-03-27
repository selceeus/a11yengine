<?php

use App\Exceptions\ScanProcessException;
use App\Jobs\RunLighthouseScanJob;
use App\Models\Agency;
use App\Models\LighthouseResult;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;
use App\Services\LighthouseRunner;
use Illuminate\Support\Facades\Log;
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
    // Pre-create a ScanPage stub as ScanPageDispatcher would
    $this->stub = ScanPage::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $this->scan->id,
        'url' => 'https://example.com/',
        'violations_count' => 0,
        'status' => \App\Enums\ScanPageStatus::Pending,
        'axe_completed' => false,
        'lighthouse_completed' => false,
    ]);
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

it('stores the form_factor on the result', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->twice()->andReturn([
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

    (new RunLighthouseScanJob($this->scan, 'https://example.com/', 'mobile'))->handle($runner);
    (new RunLighthouseScanJob($this->scan, 'https://example.com/', 'desktop'))->handle($runner);

    $results = LighthouseResult::withoutGlobalScopes()->orderBy('form_factor')->get();
    expect($results->count())->toBe(2)
        ->and($results->first()->form_factor)->toBe('desktop')
        ->and($results->last()->form_factor)->toBe('mobile');
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

// ─── lighthouse_completed tracking ───────────────────────────────────────────

it('sets lighthouse_completed=true on the ScanPage after a successful run', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andReturn([
        'url' => 'https://example.com/',
        'performance_score' => 90,
        'accessibility_score' => 80,
        'best_practices_score' => 85,
        'seo_score' => 95,
        'first_contentful_paint' => 1000.0,
        'largest_contentful_paint' => 2000.0,
        'total_blocking_time' => 100.0,
        'cumulative_layout_shift' => 0.01,
        'raw_metrics' => [],
    ]);

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    expect($this->stub->fresh()->lighthouse_completed)->toBeTrue();
});

it('sets lighthouse_completed=true on the ScanPage even when the runner fails', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andThrow(new ScanProcessException('Chrome unavailable'));

    (new RunLighthouseScanJob($this->scan, 'https://example.com/'))->handle($runner);

    expect($this->stub->fresh()->lighthouse_completed)->toBeTrue();
});

it('does nothing to lighthouse_completed when no matching ScanPage exists', function (): void {
    $runner = Mockery::mock(LighthouseRunner::class);
    $runner->expects('run')->once()->andThrow(new ScanProcessException('Chrome unavailable'));

    // Running against a URL with no matching stub should not throw
    expect(fn () => (new RunLighthouseScanJob($this->scan, 'https://example.com/no-stub'))->handle($runner))
        ->not->toThrow(Exception::class);
});
