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
     *
     * @param  string  $url  The page URL to audit.
     * @param  int  $timeout  Maximum seconds to wait for the process.
     * @return array{url: string, performance_score: int, accessibility_score: int, best_practices_score: int, seo_score: int, first_contentful_paint: float, largest_contentful_paint: float, total_blocking_time: float, cumulative_layout_shift: float, raw_metrics: array<string, mixed>}
     *
     * @throws ScanProcessException When the process fails or returns invalid output.
     */
    public function run(string $url, int $timeout): array
    {
        $result = Process::timeout($timeout)->run([
            config('lighthouse.binary'),
            $url,
            '--output=json',
            '--output-path=stdout',
            '--quiet',
            '--chrome-flags=--headless --no-sandbox --disable-gpu',
        ]);

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
