<?php

namespace App\Services;

use App\Exceptions\ScanProcessException;
use Illuminate\Support\Facades\Process;

class LighthouseRunner
{
    /**
     * Run the Lighthouse CLI against the given URL and return normalized metrics.
     *
     * The Lighthouse process is invoked with `--output=json --output-path=stdout`
     * so that the full JSON report is written to stdout. Only the eight key
     * category scores and Core Web Vital metrics are extracted and returned.
     * Pass $formFactor='desktop' to use the Lighthouse desktop preset.
     *
     * @param  string  $url  The page URL to audit.
     * @param  int  $timeout  Maximum seconds to wait for the process.
     * @param  string  $formFactor  Either 'mobile' (default) or 'desktop'.
     * @return array{url: string, performance_score: int, accessibility_score: int, best_practices_score: int, seo_score: int, first_contentful_paint: float, largest_contentful_paint: float, total_blocking_time: float, cumulative_layout_shift: float, raw_metrics: array<string, mixed>}
     *
     * @throws ScanProcessException When the process fails or returns invalid output.
     */
    public function run(string $url, int $timeout, string $formFactor = 'mobile'): array
    {
        // Give each Lighthouse process its own temp directory so concurrent
        // mobile + desktop runs do not clash on Windows (EPERM on shared temp paths).
        $tempDir = storage_path('app/lighthouse-tmp/'.uniqid('lh-', true));
        mkdir($tempDir, 0755, true);

        $command = [
            config('lighthouse.binary'),
            $url,
            '--output=json',
            '--output-path=stdout',
            '--quiet',
            '--temp-path='.$tempDir,
            '--chrome-flags=--headless --no-sandbox --disable-gpu',
        ];

        if ($chromePath = config('lighthouse.chrome_path')) {
            $command[] = '--chrome-path='.$chromePath;
        }

        if ($formFactor === 'desktop') {
            $command[] = '--preset=desktop';
        }

        $result = Process::timeout($timeout)->run($command);

        $this->removeTempDir($tempDir);

        if ($result->failed()) {
            throw new ScanProcessException(
                sprintf(
                    'Lighthouse process exited with code %d: %s',
                    $result->exitCode(),
                    trim($result->errorOutput()) ?: '(no error output)',
                ),
            );
        }

        $report = json_decode($result->output(), true);

        if (! is_array($report)) {
            throw new ScanProcessException(
                'Lighthouse returned invalid JSON. Raw output: '.substr($result->output(), 0, 500),
            );
        }

        return $this->normalize($url, $report);
    }

    /**
     * Recursively delete a temporary directory created for a Lighthouse run.
     * Failures are silenced — a leftover temp dir is not worth crashing over.
     */
    private function removeTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->removeTempDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * Extract and normalize the metrics we care about from a raw Lighthouse report.
     *
     * @param  array<string, mixed>  $report
     * @return array{url: string, performance_score: int, accessibility_score: int, best_practices_score: int, seo_score: int, first_contentful_paint: float, largest_contentful_paint: float, total_blocking_time: float, cumulative_layout_shift: float, raw_metrics: array<string, mixed>}
     */
    private function normalize(string $url, array $report): array
    {
        return [
            'url' => $url,
            'performance_score' => (int) round(($report['categories']['performance']['score'] ?? 0) * 100),
            'accessibility_score' => (int) round(($report['categories']['accessibility']['score'] ?? 0) * 100),
            'best_practices_score' => (int) round(($report['categories']['best-practices']['score'] ?? 0) * 100),
            'seo_score' => (int) round(($report['categories']['seo']['score'] ?? 0) * 100),
            'first_contentful_paint' => (float) ($report['audits']['first-contentful-paint']['numericValue'] ?? 0),
            'largest_contentful_paint' => (float) ($report['audits']['largest-contentful-paint']['numericValue'] ?? 0),
            'total_blocking_time' => (float) ($report['audits']['total-blocking-time']['numericValue'] ?? 0),
            'cumulative_layout_shift' => (float) ($report['audits']['cumulative-layout-shift']['numericValue'] ?? 0),
            'raw_metrics' => $report['audits'] ?? [],
        ];
    }
}
