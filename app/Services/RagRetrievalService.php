<?php

namespace App\Services;

use App\Models\LawsuitEmbedding;
use App\Models\RemediationEmbedding;
use App\Models\WcagEmbedding;

class RagRetrievalService
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Find the most relevant WCAG documentation chunks for the given query.
     *
     * @param  list<string>|null  $criteria  Limit to specific WCAG criteria (e.g. ['1.1.1', '1.4.3'])
     * @return list<array{criterion: string, level: string, title: string, chunk: string, score: float}>
     */
    public function findWcagChunks(string $query, int $limit = 5, ?array $criteria = null): array
    {
        $queryEmbedding = $this->embeddings->embed($query);

        $builder = WcagEmbedding::query();

        if ($criteria !== null) {
            $builder->whereIn('criterion', $criteria);
        }

        $rows = $builder->get(['criterion', 'level', 'title', 'chunk', 'embedding']);

        return $this->rankBySimilarity($rows, $queryEmbedding, $limit, function ($row): array {
            return [
                'criterion' => $row->criterion,
                'level' => $row->level,
                'title' => $row->title,
                'chunk' => $row->chunk,
            ];
        });
    }

    /**
     * Find the most relevant ADA lawsuit precedents for the given query.
     *
     * @param  list<string>|null  $industries  Limit to specific industries
     * @return list<array{case_name: string, filed_year: int, industry: string|null, outcome: string, settlement_amount: int|null, summary: string, score: float}>
     */
    public function findLawsuits(string $query, int $limit = 5, ?array $industries = null): array
    {
        $queryEmbedding = $this->embeddings->embed($query);

        $builder = LawsuitEmbedding::query();

        if ($industries !== null) {
            $builder->whereIn('industry', $industries);
        }

        $rows = $builder->get(['case_name', 'filed_year', 'industry', 'outcome', 'settlement_amount', 'summary', 'embedding']);

        return $this->rankBySimilarity($rows, $queryEmbedding, $limit, function ($row): array {
            return [
                'case_name' => $row->case_name,
                'filed_year' => $row->filed_year,
                'industry' => $row->industry,
                'outcome' => $row->outcome,
                'settlement_amount' => $row->settlement_amount,
                'summary' => $row->summary,
            ];
        });
    }

    /**
     * Find similar remediation patterns from previously resolved issues.
     *
     * @return list<array{rule_key: string, wcag_criteria: string|null, description: string, resolution: string, outcome: string|null, score: float}>
     */
    public function findSimilarRemediations(string $query, int $limit = 5): array
    {
        $queryEmbedding = $this->embeddings->embed($query);

        $rows = RemediationEmbedding::query()
            ->get(['rule_key', 'wcag_criteria', 'description', 'resolution', 'outcome', 'embedding']);

        return $this->rankBySimilarity($rows, $queryEmbedding, $limit, function ($row): array {
            return [
                'rule_key' => $row->rule_key,
                'wcag_criteria' => $row->wcag_criteria,
                'description' => $row->description,
                'resolution' => $row->resolution,
                'outcome' => $row->outcome,
            ];
        });
    }

    /**
     * Rank a collection of models by cosine similarity to the query embedding,
     * returning the top $limit results with a 'score' field appended.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $rows
     * @param  list<float>  $queryEmbedding
     * @param  callable(\Illuminate\Database\Eloquent\Model): array<string, mixed>  $toArray
     * @return list<array<string, mixed>>
     */
    private function rankBySimilarity($rows, array $queryEmbedding, int $limit, callable $toArray): array
    {
        if ($rows->isEmpty() || count($queryEmbedding) === 0) {
            return [];
        }

        $scored = $rows->map(function ($row) use ($queryEmbedding, $toArray): array {
            $embedding = is_array($row->embedding) ? $row->embedding : [];

            return array_merge(
                $toArray($row),
                ['score' => $this->embeddings->cosineSimilarity($queryEmbedding, $embedding)],
            );
        });

        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
