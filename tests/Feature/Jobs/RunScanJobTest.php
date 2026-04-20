<?php

use App\Domain\Scans\Scan as ScanDomain;
use App\Enums\ScanStatus;
use App\Exceptions\ScanProcessException;
use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use App\Services\CrawlerRunner;
use App\Services\ScanPageDispatcher;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Configure a fake Process that returns the given page results as JSON.
 *
 * @param  array<int, array{url: string, violations: array<int, mixed>}>  $pages
 * @param  array<int, string>  $pdfs
 */
function fakeCrawler(array $pages = [], array $pdfs = []): void
{
    Process::fake(['*' => Process::result(json_encode(['pages' => $pages, 'pdfs' => $pdfs]))]);
}

/**
 * Build a minimal axe-core violation for use in crawler output fixtures.
 *
 * @param  array<int, array{target: list<string>, html?: string, failureSummary?: string}>  $nodes
 * @return array{id: string, impact: string, nodes: list<mixed>}
 */
function crawlerViolation(string $id = 'color-contrast', string $impact = 'serious', array $nodes = []): array
{
    return [
        'id' => $id,
        'impact' => $impact,
        'nodes' => empty($nodes) ? [['target' => ['#el'], 'failureSummary' => 'Fix this.']] : $nodes,
    ];
}

/**
 * Build a minimal axe-core page result for use in crawler output fixtures.
 *
 * @param  array<int, mixed>  $violations
 * @return array{url: string, violations: array<int, mixed>}
 */
function crawlerPage(string $url = 'https://example.com/', array $violations = []): array
{
    return ['url' => $url, 'violations' => $violations];
}

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

    // Disable Lighthouse so only axe jobs run — keeps tests focused on the crawl pipeline
    config(['lighthouse.enabled' => false]);
});

// ─── Queue dispatching ────────────────────────────────────────────────────────

it('can be dispatched to the queue', function (): void {
    Queue::fake();

    RunScanJob::dispatch($this->scan);

    Queue::assertPushed(RunScanJob::class, fn ($job) => $job->scan->is($this->scan));
});

// ─── Scan state transitions ───────────────────────────────────────────────────

it('records started_at when the crawl begins', function (): void {
    fakeCrawler();

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    expect($this->scan->fresh()->started_at)->not->toBeNull();
});

it('transitions the scan to completed after a successful crawl', function (): void {
    fakeCrawler([crawlerPage()]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Completed);
});

it('sets pages_scanned to the number of pages in the crawler output', function (): void {
    fakeCrawler([
        crawlerPage('https://example.com/'),
        crawlerPage('https://example.com/about'),
        crawlerPage('https://example.com/contact'),
    ]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    expect($this->scan->fresh()->pages_scanned)->toBe(3);
});

it('sets total_violations to the sum of violations across all pages', function (): void {
    fakeCrawler([
        crawlerPage('https://example.com/', [
            crawlerViolation('image-alt', 'critical', [['target' => ['#a']], ['target' => ['#b']]]),
        ]),
        crawlerPage('https://example.com/about', [
            crawlerViolation('color-contrast', 'serious', [['target' => ['#c']]]),
        ]),
    ]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    // 2 nodes on page 1 + 1 node on page 2 = 3 total violation findings
    expect($this->scan->fresh()->total_violations)->toBe(3);
});

it('completes without findings when the crawler returns no violations', function (): void {
    fakeCrawler([crawlerPage('https://example.com/')]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Completed)
        ->and(Finding::query()->count())->toBe(0);
});

// ─── Crawler invocation ───────────────────────────────────────────────────────

it('invokes the crawler with the property base_url', function (): void {
    fakeCrawler();

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'https://example.com'));
});

// ─── Finding persistence ──────────────────────────────────────────────────────

it('persists a finding for every violation node across all pages', function (): void {
    fakeCrawler([
        crawlerPage('https://example.com/', [
            crawlerViolation('image-alt', 'critical', [['target' => ['#logo']], ['target' => ['#banner']]]),
        ]),
        crawlerPage('https://example.com/about', [
            crawlerViolation('color-contrast', 'serious', [['target' => ['#heading']]]),
        ]),
    ]);

    (new RunScanJob($this->scan))->handle(new ScanDomain, app(ScanPageDispatcher::class), app(CrawlerRunner::class));

    expect(Finding::query()->count())->toBe(3);
});

// ─── Process failure handling ─────────────────────────────────────────────────

it('transitions scan to failed when the crawler exits with a non-zero code', function (): void {
    Process::fake(['*' => Process::result(exitCode: 1)]);

    expect(fn () => (new RunScanJob($this->scan))->handle(
        new ScanDomain,
        app(ScanPageDispatcher::class),
        app(CrawlerRunner::class),
    ))->toThrow(ScanProcessException::class);

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Failed);
});

it('transitions scan to failed when the crawler returns invalid json', function (): void {
    Process::fake(['*' => Process::result('not valid json at all')]);

    expect(fn () => (new RunScanJob($this->scan))->handle(
        new ScanDomain,
        app(ScanPageDispatcher::class),
        app(CrawlerRunner::class),
    ))->toThrow(ScanProcessException::class);

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Failed);
});

// ─── Failed hook ─────────────────────────────────────────────────────────────

it('marks the scan as failed via the failed hook when retries are exhausted', function (): void {
    $job = new RunScanJob($this->scan);

    $job->failed(new RuntimeException('Max retries exceeded'));

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Failed);
});

it('stores the error message on the scan when the crawler fails', function (): void {
    Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'Node process crashed')]);

    expect(fn () => (new RunScanJob($this->scan))->handle(
        new ScanDomain,
        app(ScanPageDispatcher::class),
        app(CrawlerRunner::class),
    ))->toThrow(ScanProcessException::class);

    expect($this->scan->fresh()->error_message)->not->toBeNull();
});

it('stores the error message via the failed hook when retries are exhausted', function (): void {
    $job = new RunScanJob($this->scan);

    $job->failed(new RuntimeException('Max retries exceeded'));

    expect($this->scan->fresh()->error_message)->toBe('Max retries exceeded');
});
