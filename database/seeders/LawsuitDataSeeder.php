<?php

namespace Database\Seeders;

use App\Jobs\IngestLawsuitDataJob;
use Illuminate\Database\Seeder;

class LawsuitDataSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/lawsuits.json');

        if (! file_exists($path)) {
            $this->command->error("Lawsuit dataset not found at: {$path}");

            return;
        }

        $records = json_decode(file_get_contents($path), true);

        if (empty($records)) {
            $this->command->warn('No lawsuit records found in lawsuits.json.');

            return;
        }

        $this->command->info(sprintf('Dispatching %d lawsuit ingestion jobs…', count($records)));

        foreach ($records as $record) {
            IngestLawsuitDataJob::dispatch($record);
        }

        $this->command->info('All lawsuit jobs dispatched to queue.');
    }
}
