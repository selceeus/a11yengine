<?php

namespace App\Services;

use App\Domain\Scans\ScanConfig;
use App\Exceptions\ScanProcessException;
use Illuminate\Support\Facades\Process;

class CrawlerRunner
{
    /**
     * Run the Node.js axe-core crawler against the given URL and return the
     * parsed results, including discovered page results and PDF URLs.
     *
     * The crawler process is expected to write a JSON object to stdout:
     *
     * ```json
     * {
     *   "pages": [
     *     { "url": "https://example.com/page", "violations": [...] }
     *   ],
     *   "pdfs": ["https://example.com/doc.pdf"]
     * }
     * ```
     *
     * @param  string  $url  The base URL to crawl.
     * @param  int  $timeout  Maximum seconds to wait for the process.
     * @return array{pages: array<int, array{url: string, violations: array<int, mixed>}>, pdfs: array<int, string>}
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
            '--wcag-version', $config->wcagVersion,
        ];

        if ($config->orderedUrls !== []) {
            foreach ($config->orderedUrls as $orderedUrl) {
                $cmd[] = '--urls';
                $cmd[] = $orderedUrl;
            }
        } else {
            $cmd[] = '--max-pages';
            $cmd[] = (string) $config->maxPages;
            $cmd[] = '--max-depth';
            $cmd[] = (string) $config->maxDepth;

            foreach ($config->includePatterns as $pattern) {
                $cmd[] = '--include';
                $cmd[] = $pattern;
            }

            foreach ($config->excludePatterns as $pattern) {
                $cmd[] = '--exclude';
                $cmd[] = $pattern;
            }
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

        if (! is_array($pages) || ! array_key_exists('pages', $pages) || ! array_key_exists('pdfs', $pages)) {
            throw new ScanProcessException(
                'Crawler returned invalid JSON. Raw output: '.substr($result->output(), 0, 500),
            );
        }

        return $pages;
    }
}
