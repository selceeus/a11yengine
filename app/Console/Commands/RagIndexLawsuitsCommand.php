<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RagIndexLawsuitsCommand extends Command
{
    protected $signature = 'rag:index-lawsuits
                            {--fresh : Truncate lawsuit_embeddings before indexing}';

    protected $description = 'Dispatch queued jobs to embed ADA lawsuit records into the vector store';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            \App\Models\LawsuitEmbedding::query()->truncate();
            $this->info('Cleared existing lawsuit embeddings.');
        }

        $this->call('db:seed', ['--class' => 'LawsuitDataSeeder', '--no-interaction' => true]);

        $this->info('Lawsuit ingestion jobs dispatched. Run the queue worker to process them.');

        return self::SUCCESS;
    }
}
