<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RagReindexCommand extends Command
{
    protected $signature = 'rag:reindex
                            {--only= : Comma-separated stores to reindex: wcag, lawsuits, remediations}
                            {--fresh : Pass --fresh to the wcag and lawsuits indexers (truncates before reindexing)}
                            {--limit=0 : Max issues per run passed to rag:reindex-remediations}';

    protected $description = 'Re-index one or more RAG stores (wcag, lawsuits, remediations)';

    /** @var array<string, string> */
    private const STORE_COMMANDS = [
        'wcag' => 'rag:index-wcag',
        'lawsuits' => 'rag:index-lawsuits',
        'remediations' => 'rag:reindex-remediations',
    ];

    public function handle(): int
    {
        $only = $this->option('only');

        $stores = $only
            ? array_map('trim', explode(',', $only))
            : array_keys(self::STORE_COMMANDS);

        $invalid = array_diff($stores, array_keys(self::STORE_COMMANDS));

        if (! empty($invalid)) {
            $this->error('Unknown store(s): '.implode(', ', $invalid));
            $this->line('Valid values: wcag, lawsuits, remediations');

            return self::FAILURE;
        }

        $this->info(sprintf('Re-indexing RAG store(s): %s', implode(', ', $stores)));

        $exitCode = self::SUCCESS;

        foreach ($stores as $store) {
            $command = self::STORE_COMMANDS[$store];
            $options = ['--no-interaction' => true];

            if ($store === 'wcag' || $store === 'lawsuits') {
                if ($this->option('fresh')) {
                    $options['--fresh'] = true;
                } else {
                    $options['--skip-if-indexed'] = true;
                }
            }

            if ($store === 'remediations') {
                $options['--limit'] = $this->option('limit');
            }

            $result = $this->call($command, $options);

            if ($result !== self::SUCCESS) {
                $this->error("rag:reindex — {$command} returned exit code {$result}");
                $exitCode = $result;
            }
        }

        return $exitCode;
    }
}
