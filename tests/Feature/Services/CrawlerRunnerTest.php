<?php

use App\Domain\Issues\ProcessHtmlScan;
use App\Domain\Scans\Scan as ScanDomain;
use App\Exceptions\ScanProcessException;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use App\Services\CrawlerRunner;
use Illuminate\Support\Facades\Process;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Make a fake Process result that outputs the given pages as JSON.
 *
 * @param  array<int, mixed>  $pages
 */
function crawlerOutput(array $pages): void
{
    Process::fake(['*' => Process::result(json_encode($pages))]);
}

/**
 * Build a minimal axe-core page result.
 *
 * @param  array<int, mixed>  $violations
 * @return array{url: string, violations: array<int, mixed>}
 */
function pageResult(string $url = 'https://example.com/', array $violations = []): array
{
    return ['url' => $url, 'violations' => $violations];
}

/**
 * Build a minimal axe-core violation.
 *
 * @return array{id: string, impact: string, nodes: list<array{target: list<string>, failureSummary: string}>}
 */
function violation(
    string $id = 'color-contrast',
    string $impact = 'serious',
    array $nodes = [],
): array {
    return [
        'id' => $id,
        'impact' => $impact,
        'tags' => ['wcag2a', 'wcag2aa'],
        'nodes' => empty($nodes)
            ? [['target' => ['#el'], 'html' => '<p></p>', 'failureSummary' => 'Fix this.']]
            : $nodes,
    ];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->runner = app(CrawlerRunner::class);
});

// ─── Process command ──────────────────────────────────────────────────────────

it('invokes node as the executable', function (): void {
    crawlerOutput([]);

    $this->runner->run('https://example.com', 60);

    Process::assertRan(fn ($process) => ($process->command[0] ?? null) === 'node');
});

it('passes the configured script path as the second argument', function (): void {
    crawlerOutput([]);

    $this->runner->run('https://example.com', 60);

    Process::assertRan(
        fn ($process) => ($process->command[1] ?? null) === config('crawler.script_path')
    );
});

it('passes the target URL as the third argument', function (): void {
    crawlerOutput([]);

    $this->runner->run('https://example.com/target', 60);

    Process::assertRan(
        fn ($process) => ($process->command[2] ?? null) === 'https://example.com/target'
    );
});

it('builds the full command array with the default config flags', function (): void {
    crawlerOutput([]);

    $url = 'https://example.com';
    $this->runner->run($url, 60);

    Process::assertRan(function ($process) use ($url) {
        return $process->command === [
            'node',
            config('crawler.script_path'),
            $url,
            '--max-pages', '50',
            '--max-depth', '5',
            '--wcag-version', 'wcag21',
        ];
    });
});

it('applies the supplied timeout to the process', function (): void {
    crawlerOutput([]);

    $this->runner->run('https://example.com', 120);

    Process::assertRan(fn ($process) => $process->timeout === 120);
});

// ─── Return value ─────────────────────────────────────────────────────────────

it('returns an empty array when the crawler reports no pages', function (): void {
    crawlerOutput([]);

    $result = $this->runner->run('https://example.com', 60);

    expect($result)->toBeArray()->toBeEmpty();
});

it('returns the parsed page results array on success', function (): void {
    crawlerOutput([
        pageResult('https://example.com/', [violation()]),
        pageResult('https://example.com/about'),
    ]);

    $result = $this->runner->run('https://example.com', 60);

    expect($result)->toHaveCount(2)
        ->and($result[0]['url'])->toBe('https://example.com/')
        ->and($result[0]['violations'])->toHaveCount(1)
        ->and($result[1]['url'])->toBe('https://example.com/about');
});

it('preserves violation id, impact, tags, and nodes from the crawler output', function (): void {
    crawlerOutput([pageResult('https://example.com/', [violation('image-alt', 'critical')])]);

    $result = $this->runner->run('https://example.com', 60);

    $v = $result[0]['violations'][0];
    expect($v['id'])->toBe('image-alt')
        ->and($v['impact'])->toBe('critical')
        ->and($v['tags'])->toContain('wcag2a');
});

// ─── Process failure ──────────────────────────────────────────────────────────

it('throws ScanProcessException when the process exits with a non-zero code', function (): void {
    Process::fake(['*' => Process::result(exitCode: 1)]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class);
});

it('includes the exit code in the exception message', function (): void {
    Process::fake(['*' => Process::result(exitCode: 2)]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'code 2');
});

it('includes the process stderr in the exception message when available', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Cannot find module', exitCode: 1)]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'Cannot find module');
});

it('uses a fallback message when stderr is empty', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: '', exitCode: 1)]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'no error output');
});

it('throws ScanProcessException when the crawler returns non-JSON stdout', function (): void {
    Process::fake(['*' => Process::result('not valid json')]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'invalid JSON');
});

it('throws ScanProcessException when the crawler returns a bare JSON string', function (): void {
    Process::fake(['*' => Process::result('"just a string"')]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'invalid JSON');
});

it('throws ScanProcessException when the crawler returns a bare JSON number', function (): void {
    Process::fake(['*' => Process::result('42')]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'invalid JSON');
});

it('includes up to 500 chars of raw output in the invalid JSON exception message', function (): void {
    $garbage = str_repeat('X', 600);
    Process::fake(['*' => Process::result($garbage)]);

    try {
        $this->runner->run('https://example.com', 60);
    } catch (ScanProcessException $e) {
        expect(mb_strlen($e->getMessage()))->toBeLessThan(600);
    }
});

// ─── Full pipeline: CrawlerRunner → ProcessHtmlScan ──────────────────────────

describe('pipeline: crawler JSON → ProcessHtmlScan → database', function (): void {
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

        $this->scanDomain = new ScanDomain;
        $this->processHtmlScan = app(ProcessHtmlScan::class);
        $this->runner = app(CrawlerRunner::class);
    });

    it('persists one Finding per violation node received from the crawler', function (): void {
        crawlerOutput([
            pageResult('https://example.com/', [
                violation('image-alt', 'critical', [
                    ['target' => ['#logo'], 'html' => '<img>', 'failureSummary' => 'Add alt text.'],
                    ['target' => ['#hero'], 'html' => '<img>', 'failureSummary' => 'Add alt text.'],
                ]),
                violation('color-contrast', 'serious'),
            ]),
        ]);

        $this->scanDomain->start($this->scan);

        $pages = $this->runner->run($this->property->base_url, 60);

        foreach ($pages as $page) {
            $this->processHtmlScan->handle($this->scan, $page);
        }

        // 2 nodes for image-alt + 1 default node for color-contrast = 3 findings
        expect(Finding::query()->count())->toBe(3);
    });

    it('creates an Issue for each unique rule_key + page_url combination', function (): void {
        crawlerOutput([
            pageResult('https://example.com/', [
                violation('image-alt', 'critical'),
                violation('color-contrast', 'serious'),
            ]),
        ]);

        $this->scanDomain->start($this->scan);

        $pages = $this->runner->run($this->property->base_url, 60);

        foreach ($pages as $page) {
            $this->processHtmlScan->handle($this->scan, $page);
        }

        expect(Issue::query()->count())->toBe(2);
    });

    it('sets the Finding rule_key and element_identifier from the crawler node', function (): void {
        crawlerOutput([
            pageResult('https://example.com/', [
                violation('label', 'moderate', [
                    ['target' => ['#my-input'], 'html' => '<input>', 'failureSummary' => 'Add a label.'],
                ]),
            ]),
        ]);

        $this->scanDomain->start($this->scan);

        $pages = $this->runner->run($this->property->base_url, 60);

        foreach ($pages as $page) {
            $this->processHtmlScan->handle($this->scan, $page);
        }

        $finding = Finding::query()->first();
        expect($finding->rule_key)->toBe('label')
            ->and($finding->element_identifier)->toBe('#my-input');
    });

    it('aggregates violation counts across multiple pages', function (): void {
        crawlerOutput([
            pageResult('https://example.com/', [violation('image-alt', 'critical')]),
            pageResult('https://example.com/about', [violation('color-contrast', 'serious')]),
            pageResult('https://example.com/contact', [violation('label', 'moderate')]),
        ]);

        $this->scanDomain->start($this->scan);

        $totalViolations = 0;
        $pagesScanned = 0;

        $pages = $this->runner->run($this->property->base_url, 60);

        foreach ($pages as $page) {
            $scanPage = $this->processHtmlScan->handle($this->scan, $page);
            $pagesScanned++;
            $totalViolations += $scanPage->violations_count;
        }

        $this->scanDomain->complete($this->scan, $pagesScanned, $totalViolations);

        $fresh = $this->scan->fresh();
        expect($fresh->pages_scanned)->toBe(3)
            ->and($fresh->total_violations)->toBe(3);
    });

    it('produces no Findings or Issues when the crawler returns no violations', function (): void {
        crawlerOutput([
            pageResult('https://example.com/'),
            pageResult('https://example.com/about'),
        ]);

        $this->scanDomain->start($this->scan);

        foreach ($this->runner->run($this->property->base_url, 60) as $page) {
            $this->processHtmlScan->handle($this->scan, $page);
        }

        expect(Finding::query()->count())->toBe(0)
            ->and(Issue::query()->count())->toBe(0);
    });

    it('does not create a duplicate Issue when the same rule fires on the same page twice', function (): void {
        // Simulates a second scan run encountering an already-open issue. Each scan
        // has its own ID so fingerprints don't collide within a single scan.
        $scan2 = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

        $pageData = pageResult('https://example.com/', [violation('color-contrast', 'serious')]);

        crawlerOutput([$pageData]);
        foreach ($this->runner->run($this->property->base_url, 60) as $page) {
            $this->processHtmlScan->handle($this->scan, $page);
        }

        // Second scan against the same page — issue should be incremented, not duplicated
        crawlerOutput([$pageData]);
        foreach ($this->runner->run($this->property->base_url, 60) as $page) {
            $this->processHtmlScan->handle($scan2, $page);
        }

        expect(Issue::query()->count())->toBe(1)
            ->and(Issue::query()->first()->occurrence_count)->toBe(2);
    });
});
