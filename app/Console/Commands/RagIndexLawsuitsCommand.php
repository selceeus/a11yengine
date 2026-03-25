<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RagIndexLawsuitsCommand extends Command
{
    protected $signature = 'rag:index-lawsuits
                            {--fresh : Truncate lawsuit_embeddings before indexing}
                            {--skip-if-indexed : Skip if the expected number of lawsuit records are already indexed}';

    protected $description = 'Dispatch queued jobs to embed ADA lawsuit records into the vector store';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            \App\Models\LawsuitEmbedding::query()->truncate();
            $this->info('Cleared existing lawsuit embeddings.');
        }

        if ($this->option('skip-if-indexed')) {
            $path = database_path('data/lawsuits.json');
            $expected = file_exists($path) ? count(json_decode(file_get_contents($path), true) ?? []) : 0;
            $indexed = \App\Models\LawsuitEmbedding::query()->count();

            if ($expected > 0 && $indexed >= $expected) {
                $this->info("Lawsuit embeddings already fully indexed ({$indexed}/{$expected} records). Skipping.");

                return self::SUCCESS;
            }

            if ($expected > 0) {
                $this->info("Lawsuit embeddings partially indexed ({$indexed}/{$expected} records). Continuing.");
            }
        }

        $this->call('db:seed', ['--class' => 'LawsuitDataSeeder', '--no-interaction' => true]);

        $this->info('Lawsuit ingestion jobs dispatched. Run the queue worker to process them.');

        return self::SUCCESS;
    }
}
