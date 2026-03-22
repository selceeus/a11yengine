<?php

namespace App\Services;

use App\Domain\Scans\ScanConfig;
use App\Exceptions\ScanProcessException;
use Illuminate\Support\Facades\Process;

class CrawlerRunner
{
    /**
     * Run the Node.js axe-core crawler against the given URL and return the
     * parsed page results.
     *
     * The crawler process is expected to write a JSON array to stdout where
     * each element represents a single scanned page:
     *
     * ```json
     * [
     *   {
     *     "url": "https://example.com/page",
     *     "violations": [
     *       { "id": "color-contrast", "impact": "serious", "nodes": [...] }
     *     ]
     *   }
     * ]
     * ```
     *
     * @param  string  $url  The base URL to crawl.
     * @param  int  $timeout  Maximum seconds to wait for the process.
     * @return array<int, array{url: string, violations: array<int, mixed>}>
     *
     * @throws ScanProcessException When the process fails or returns invalid output.
     */
    public function run(string $url, int $timeout, ?ScanConfig $config = null): array
    {
        $config ??= new ScanConfig;

        $cmd = [
            'node',
            config('crawler.script_path'),
            $url,
            '--max-pages', (string) $config->maxPages,
            '--max-depth', (string) $config->maxDepth,
            '--wcag-version', $config->wcagVersion,
        ];

        foreach ($config->includePatterns as $pattern) {
            $cmd[] = '--include';
            $cmd[] = $pattern;
        }

        foreach ($config->excludePatterns as $pattern) {
            $cmd[] = '--exclude';
            $cmd[] = $pattern;
        }

        $result = Process::timeout($timeout)->run($cmd);

        if ($result->failed()) {
            throw new ScanProcessException(
                sprintf(
                    'Crawler process exited with code %d: %s',
                    $result->exitCode(),
                    trim($result->errorOutput()) ?: '(no error output)',
                ),
            );
        }

        $pages = json_decode($result->output(), true);

        if (! is_array($pages)) {
            throw new ScanProcessException(
                'Crawler returned invalid JSON. Raw output: '.substr($result->output(), 0, 500),
            );
        }

        return $pages;
    }
}
