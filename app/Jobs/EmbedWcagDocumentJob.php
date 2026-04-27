<?php

namespace App\Jobs;

use App\Models\WcagEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EmbedWcagDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * @param  array{criterion: string, level: string, title: string, chunk: string, chunk_index: int}  $chunk
     */
    public function __construct(public readonly array $chunk) {}

    public function handle(EmbeddingService $embeddingService): void
    {
        $embedding = $embeddingService->embed(
            sprintf('%s %s %s', $this->chunk['criterion'], $this->chunk['title'], $this->chunk['chunk'])
        );

        WcagEmbedding::updateOrCreate(
            [
                'criterion' => $this->chunk['criterion'],
                'chunk_index' => $this->chunk['chunk_index'],
            ],
            [
                'level' => $this->chunk['level'],
                'title' => $this->chunk['title'],
                'chunk' => $this->chunk['chunk'],
                'embedding' => $embedding,
                'metadata' => [
                    'source' => 'wcag_criteria.json',
                    'indexed_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    public function failed(Throwable $e): void
    {
        logger()->error('EmbedWcagDocumentJob failed', [
            'criterion' => $this->chunk['criterion'],
            'chunk_index' => $this->chunk['chunk_index'],
            'error' => $e->getMessage(),
        ]);
    }
}
