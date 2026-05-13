<?php

namespace App\Domain\Issues;

use App\Models\Scan;

class ProcessScreenReaderScan
{
    public function __construct(
        private readonly ProcessHtmlScan $processHtmlScan,
    ) {}

    /**
     * Process screen reader violations for a single page, persisting them
     * through the same Finding + Issue pipeline used for axe-core violations.
     *
     * SR violations are produced by crawler/screenReaderRunner.js and share
     * the exact axe-core violation shape, so they flow through ProcessHtmlScan
     * unchanged. Rule keys are prefixed with "sr-" to distinguish them.
     *
     * @param  array<int, array{id: string, impact: string|null, description?: string, helpUrl?: string, tags?: list<string>, nodes: list<array{target: list<string>, html?: string, failureSummary?: string}>}>  $violations
     */
    public function handle(Scan $scan, string $url, array $violations): void
    {
        if (empty($violations)) {
            return;
        }

        $this->processHtmlScan->handle($scan, [
            'url' => $url,
            'violations' => $violations,
        ]);
    }
}
