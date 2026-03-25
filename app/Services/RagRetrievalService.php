<?php

namespace App\Services;

use App\Models\LawsuitEmbedding;
use App\Models\RemediationEmbedding;
use App\Models\WcagEmbedding;
use Illuminate\Database\Eloquent\Builder;

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

        if (empty($queryEmbedding)) {
            return [];
        }

        $builder = WcagEmbedding::query()
            ->select(['criterion', 'level', 'title', 'chunk']);

        if ($criteria !== null) {
            $builder->whereIn('criterion', $criteria);
        }

        return $this->nearestNeighbors($builder, $queryEmbedding, $limit)
            ->map(fn ($row) => [
                'criterion' => $row->criterion,
                'level' => $row->level,
                'title' => $row->title,
                'chunk' => $row->chunk,
                'score' => $row->score,
            ])
            ->all();
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

        if (empty($queryEmbedding)) {
            return [];
        }

        $builder = LawsuitEmbedding::query()
            ->select(['case_name', 'filed_year', 'industry', 'outcome', 'settlement_amount', 'summary']);

        if ($industries !== null) {
            $builder->whereIn('industry', $industries);
        }

        return $this->nearestNeighbors($builder, $queryEmbedding, $limit)
            ->map(fn ($row) => [
                'case_name' => $row->case_name,
                'filed_year' => $row->filed_year,
                'industry' => $row->industry,
                'outcome' => $row->outcome,
                'settlement_amount' => $row->settlement_amount,
                'summary' => $row->summary,
                'score' => $row->score,
            ])
            ->all();
    }

    /**
     * Find similar remediation patterns from previously resolved issues.
     *
     * @return list<array{rule_key: string, wcag_criteria: string|null, description: string, resolution: string, outcome: string|null, resolved_count: int, score: float}>
     */
    public function findSimilarRemediations(string $query, int $limit = 5): array
    {
        $queryEmbedding = $this->embeddings->embed($query);

        if (empty($queryEmbedding)) {
            return [];
        }

        $builder = RemediationEmbedding::query()
            ->select(['rule_key', 'wcag_criteria', 'description', 'resolution', 'outcome']);

        $ranked = $this->nearestNeighbors($builder, $queryEmbedding, $limit)
            ->map(fn ($row) => [
                'rule_key' => $row->rule_key,
                'wcag_criteria' => $row->wcag_criteria,
                'description' => $row->description,
                'resolution' => $row->resolution,
                'outcome' => $row->outcome,
                'score' => $row->score,
            ])
            ->all();

        if (empty($ranked)) {
            return [];
        }

        $counts = RemediationEmbedding::query()
            ->whereIn('rule_key', array_column($ranked, 'rule_key'))
            ->selectRaw('rule_key, COUNT(*) as total')
            ->groupBy('rule_key')
            ->pluck('total', 'rule_key')
            ->toArray();

        return array_map(function (array $r) use ($counts): array {
            $r['resolved_count'] = (int) ($counts[$r['rule_key']] ?? 1);

            return $r;
        }, $ranked);
    }

    /**
     * Use pgvector's cosine distance operator (<=>) to find the nearest
     * neighbors in the database, returning results with a similarity score.
     *
     * Score = 1 - cosine_distance (so higher = more similar).
     *
     * @param  list<float>  $queryEmbedding
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function nearestNeighbors(Builder $builder, array $queryEmbedding, int $limit): \Illuminate\Support\Collection
    {
        $vectorLiteral = '['.implode(',', $queryEmbedding).']';

        return $builder
            ->selectRaw('(1 - (embedding <=> ?)) as score', [$vectorLiteral])
            ->orderByRaw('embedding <=> ?', [$vectorLiteral])
            ->limit($limit)
            ->get();
    }
}
