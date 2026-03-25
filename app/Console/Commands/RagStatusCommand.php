<?php

namespace App\Console\Commands;

use App\Models\LawsuitEmbedding;
use App\Models\RemediationEmbedding;
use App\Models\WcagEmbedding;
use Illuminate\Console\Command;

class RagStatusCommand extends Command
{
    protected $signature = 'rag:status';

    protected $description = 'Display the current status of all RAG embedding tables';

    public function handle(): int
    {
        $this->info('RAG Vector Store Status');
        $this->line(str_repeat('─', 60));

        $tables = [
            [
                'table' => 'wcag_embeddings',
                'label' => 'WCAG Criteria Chunks',
                'count' => WcagEmbedding::query()->count(),
                'last_updated' => WcagEmbedding::query()->max('updated_at'),
            ],
            [
                'table' => 'lawsuit_embeddings',
                'label' => 'ADA Lawsuit Records',
                'count' => LawsuitEmbedding::query()->count(),
                'last_updated' => LawsuitEmbedding::query()->max('updated_at'),
            ],
            [
                'table' => 'remediation_embeddings',
                'label' => 'Remediation Patterns',
                'count' => RemediationEmbedding::query()->count(),
                'last_updated' => RemediationEmbedding::query()->max('updated_at'),
            ],
        ];

        $this->table(
            ['Store', 'Records', 'Last Indexed'],
            array_map(fn (array $row) => [
                $row['label'],
                number_format($row['count']),
                $row['last_updated'] ? \Carbon\Carbon::parse($row['last_updated'])->diffForHumans() : 'never',
            ], $tables)
        );

        $totalRecords = array_sum(array_column($tables, 'count'));
        $this->line('');
        $this->line(sprintf('Total indexed records: <comment>%s</comment>', number_format($totalRecords)));

        if ($totalRecords === 0) {
            $this->newLine();
            $this->warn('No embeddings indexed yet. Run:');
            $this->line('  php artisan rag:index-wcag');
            $this->line('  php artisan rag:index-lawsuits');
        }

        return self::SUCCESS;
    }
}
