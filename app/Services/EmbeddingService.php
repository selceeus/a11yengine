<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    private const MODEL = 'text-embedding-3-small';

    private const DIMENSIONS = 1536;

    private PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::withToken(config('ai.embeddings.key'))
            ->baseUrl('https://api.openai.com/v1')
            ->timeout(30);
    }

    /**
     * Generate a normalised embedding vector for the given text.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $response = $this->http->post('/embeddings', [
            'model' => self::MODEL,
            'input' => $this->truncate($text),
        ]);

        $response->throw();

        return $response->json('data.0.embedding') ?? [];
    }

    /**
     * Compute the cosine similarity between two embedding vectors.
     * Returns a value in [-1, 1]; higher means more similar.
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /**
     * Truncate text to stay within the model's token limit (~8191 tokens ≈ 32 000 chars).
     */
    private function truncate(string $text, int $maxChars = 30000): string
    {
        return mb_strlen($text) > $maxChars
            ? mb_substr($text, 0, $maxChars)
            : $text;
    }

    public function dimensions(): int
    {
        return self::DIMENSIONS;
    }
}
