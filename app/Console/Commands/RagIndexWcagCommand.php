<?php

namespace App\Console\Commands;

use App\Jobs\EmbedWcagDocumentJob;
use Illuminate\Console\Command;

class RagIndexWcagCommand extends Command
{
    protected $signature = 'rag:index-wcag
                            {--fresh : Truncate wcag_embeddings before indexing}
                            {--skip-if-indexed : Skip if all expected chunks are already indexed}';

    protected $description = 'Dispatch queued jobs to embed all WCAG criteria chunks into the vector store';

    public function handle(): int
    {
        $path = database_path('data/wcag_criteria.json');

        if (! file_exists($path)) {
            $this->error("WCAG criteria dataset not found at: {$path}");

            return self::FAILURE;
        }

        $criteria = json_decode(file_get_contents($path), true);

        if (empty($criteria)) {
            $this->warn('No criteria found in wcag_criteria.json.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            \App\Models\WcagEmbedding::query()->truncate();
            $this->info('Cleared existing WCAG embeddings.');
        }

        $totalChunks = array_sum(array_map(fn (array $c) => count($c['chunks']), $criteria));

        if ($this->option('skip-if-indexed')) {
            $indexed = \App\Models\WcagEmbedding::query()->count();

            if ($indexed >= $totalChunks) {
                $this->info("WCAG embeddings already fully indexed ({$indexed}/{$totalChunks} chunks). Skipping.");

                return self::SUCCESS;
            }

            $this->info("WCAG embeddings partially indexed ({$indexed}/{$totalChunks} chunks). Continuing.");
        }

        $this->info(sprintf(
            'Dispatching %d chunk jobs across %d WCAG criteria…',
            $totalChunks,
            count($criteria)
        ));

        $bar = $this->output->createProgressBar($totalChunks);
        $bar->start();

        foreach ($criteria as $criterion) {
            foreach ($criterion['chunks'] as $index => $chunk) {
                EmbedWcagDocumentJob::dispatch([
                    'criterion' => $criterion['criterion'],
                    'chunk_index' => $index,
                    'level' => $criterion['level'],
                    'title' => $criterion['title'],
                    'chunk' => $chunk,
                ]);

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('All WCAG embedding jobs dispatched. Run the queue worker to process them.');

        return self::SUCCESS;
    }
}
