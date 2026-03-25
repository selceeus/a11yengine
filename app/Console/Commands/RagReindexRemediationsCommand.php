<?php

namespace App\Console\Commands;

use App\Jobs\IndexRemediationPatternJob;
use App\Models\Issue;
use App\Models\RemediationEmbedding;
use Illuminate\Console\Command;

class RagReindexRemediationsCommand extends Command
{
    protected $signature = 'rag:reindex-remediations
                            {--limit=0 : Max issues to process in one run (0 = no limit)}';

    protected $description = 'Index resolved issues with AI suggestions that are not yet in the remediation vector store';

    public function handle(): int
    {
        $indexedIssueIds = RemediationEmbedding::query()
            ->whereNotNull('issue_id')
            ->pluck('issue_id')
            ->all();

        $query = Issue::withoutGlobalScopes()
            ->whereNotNull('ai_suggestions')
            ->whereNotIn('id', $indexedIssueIds);

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $issues = $query->get(['id', 'rule_key', 'wcag_criteria', 'ai_suggestions', 'ai_remediation_status']);

        if ($issues->isEmpty()) {
            $this->info('All AI-resolved issues are already indexed. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Dispatching %d remediation indexing jobs…', $issues->count()));

        $bar = $this->output->createProgressBar($issues->count());
        $bar->start();

        foreach ($issues as $issue) {
            IndexRemediationPatternJob::dispatch($issue);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Jobs dispatched. Run the queue worker to process them.');

        return self::SUCCESS;
    }
}
