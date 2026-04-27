<?php

namespace App\Jobs;

use App\Models\LawsuitEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IngestLawsuitDataJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * @param  array{case_name: string, filed_year: int, industry: string, violation_type: string, wcag_criteria: string[], outcome: string, settlement_amount: int|null, summary: string}  $record
     */
    public function __construct(public readonly array $record) {}

    public function handle(EmbeddingService $embeddingService): void
    {
        $textToEmbed = sprintf(
            '%s %s %s %s %s',
            $this->record['case_name'],
            $this->record['violation_type'],
            implode(' ', $this->record['wcag_criteria'] ?? []),
            $this->record['outcome'],
            $this->record['summary']
        );

        $embedding = $embeddingService->embed($textToEmbed);

        LawsuitEmbedding::updateOrCreate(
            ['case_name' => $this->record['case_name']],
            [
                'filed_year' => $this->record['filed_year'],
                'industry' => $this->record['industry'],
                'violation_type' => $this->record['violation_type'],
                'wcag_criteria' => $this->record['wcag_criteria'] ?? [],
                'outcome' => $this->record['outcome'],
                'settlement_amount' => $this->record['settlement_amount'],
                'summary' => $this->record['summary'],
                'embedding' => $embedding,
                'metadata' => [
                    'source' => 'lawsuits.json',
                    'indexed_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    public function failed(Throwable $e): void
    {
        logger()->error('IngestLawsuitDataJob failed', [
            'case_name' => $this->record['case_name'] ?? 'unknown',
            'error' => $e->getMessage(),
        ]);
    }
}
