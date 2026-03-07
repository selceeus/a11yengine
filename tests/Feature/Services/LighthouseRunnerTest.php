<?php

use App\Exceptions\ScanProcessException;
use App\Services\LighthouseRunner;
use Illuminate\Support\Facades\Process;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Make a fake Process result that outputs the given Lighthouse report as JSON.
 *
 * @param  array<string, mixed>  $report
 */
function lighthouseOutput(array $report): void
{
    Process::fake(['*' => Process::result(json_encode($report))]);
}

/**
 * Build a minimal valid Lighthouse JSON report.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lighthouseReport(array $overrides = []): array
{
    return array_replace_recursive([
        'categories' => [
            'performance' => ['score' => 0.92],
            'accessibility' => ['score' => 0.78],
            'best-practices' => ['score' => 0.85],
            'seo' => ['score' => 0.95],
        ],
        'audits' => [
            'first-contentful-paint' => ['numericValue' => 1234.5],
            'largest-contentful-paint' => ['numericValue' => 2500.0],
            'total-blocking-time' => ['numericValue' => 150.0],
            'cumulative-layout-shift' => ['numericValue' => 0.05],
        ],
    ], $overrides);
}

// ─── Setup ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->runner = app(LighthouseRunner::class);
});

// ─── Process command ──────────────────────────────────────────────────────────

it('invokes the configured lighthouse binary', function (): void {
    lighthouseOutput(lighthouseReport());

    $this->runner->run('https://example.com', 60);

    Process::assertRan(fn ($process) => ($process->command[0] ?? null) === config('lighthouse.binary'));
});

it('passes the target URL as the second argument', function (): void {
    lighthouseOutput(lighthouseReport());

    $this->runner->run('https://example.com/target', 60);

    Process::assertRan(fn ($process) => ($process->command[1] ?? null) === 'https://example.com/target');
});

it('includes --output=json flag', function (): void {
    lighthouseOutput(lighthouseReport());

    $this->runner->run('https://example.com', 60);

    Process::assertRan(fn ($process) => in_array('--output=json', (array) $process->command, true));
});

it('includes --output-path=stdout flag', function (): void {
    lighthouseOutput(lighthouseReport());

    $this->runner->run('https://example.com', 60);

    Process::assertRan(fn ($process) => in_array('--output-path=stdout', (array) $process->command, true));
});

it('applies the supplied timeout to the process', function (): void {
    lighthouseOutput(lighthouseReport());

    $this->runner->run('https://example.com', 90);

    Process::assertRan(fn ($process) => $process->timeout === 90);
});

// ─── Return value — scores ────────────────────────────────────────────────────

it('converts the performance score to an integer out of 100', function (): void {
    lighthouseOutput(lighthouseReport(['categories' => ['performance' => ['score' => 0.92]]]));

    $result = $this->runner->run('https://example.com', 60);

    expect($result['performance_score'])->toBe(92);
});

it('converts the accessibility score to an integer out of 100', function (): void {
    lighthouseOutput(lighthouseReport(['categories' => ['accessibility' => ['score' => 0.78]]]));

    $result = $this->runner->run('https://example.com', 60);

    expect($result['accessibility_score'])->toBe(78);
});

it('converts the best-practices score to an integer out of 100', function (): void {
    lighthouseOutput(lighthouseReport(['categories' => ['best-practices' => ['score' => 0.85]]]));

    $result = $this->runner->run('https://example.com', 60);

    expect($result['best_practices_score'])->toBe(85);
});

it('converts the seo score to an integer out of 100', function (): void {
    lighthouseOutput(lighthouseReport(['categories' => ['seo' => ['score' => 0.95]]]));

    $result = $this->runner->run('https://example.com', 60);

    expect($result['seo_score'])->toBe(95);
});

it('rounds fractional scores instead of truncating', function (): void {
    lighthouseOutput(lighthouseReport(['categories' => ['performance' => ['score' => 0.925]]]));

    $result = $this->runner->run('https://example.com', 60);

    expect($result['performance_score'])->toBe(93);
});

// ─── Return value — metrics ───────────────────────────────────────────────────

it('extracts first_contentful_paint from audits', function (): void {
    lighthouseOutput(lighthouseReport());

    $result = $this->runner->run('https://example.com', 60);

    expect($result['first_contentful_paint'])->toBe(1234.5);
});

it('extracts largest_contentful_paint from audits', function (): void {
    lighthouseOutput(lighthouseReport());

    $result = $this->runner->run('https://example.com', 60);

    expect($result['largest_contentful_paint'])->toBe(2500.0);
});

it('extracts total_blocking_time from audits', function (): void {
    lighthouseOutput(lighthouseReport());

    $result = $this->runner->run('https://example.com', 60);

    expect($result['total_blocking_time'])->toBe(150.0);
});

it('extracts cumulative_layout_shift from audits', function (): void {
    lighthouseOutput(lighthouseReport());

    $result = $this->runner->run('https://example.com', 60);

    expect($result['cumulative_layout_shift'])->toBe(0.05);
});

it('includes the url in the return value', function (): void {
    lighthouseOutput(lighthouseReport());

    $result = $this->runner->run('https://example.com/page', 60);

    expect($result['url'])->toBe('https://example.com/page');
});

it('populates raw_metrics from the audits section', function (): void {
    lighthouseOutput(lighthouseReport());

    $result = $this->runner->run('https://example.com', 60);

    expect($result['raw_metrics'])->toBeArray()->toHaveKey('first-contentful-paint');
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
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Chrome failed to launch', exitCode: 1)]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'Chrome failed to launch');
});

it('uses a fallback message when stderr is empty', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: '', exitCode: 1)]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'no error output');
});

it('throws ScanProcessException when lighthouse returns non-JSON stdout', function (): void {
    Process::fake(['*' => Process::result('not valid json')]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'invalid JSON');
});

it('throws ScanProcessException when lighthouse returns a bare JSON string', function (): void {
    Process::fake(['*' => Process::result('"just a string"')]);

    expect(fn () => $this->runner->run('https://example.com', 60))
        ->toThrow(ScanProcessException::class, 'invalid JSON');
});
