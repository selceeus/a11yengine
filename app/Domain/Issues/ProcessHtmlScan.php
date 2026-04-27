<?php

namespace App\Domain\Issues;

use App\Domain\Scans\ScanPage as ScanPageDomain;
use App\Enums\IssueStatus;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class ProcessHtmlScan
{
    public function __construct(
        private readonly ScanPageDomain $scanPage,
        private readonly IssueNormalizer $normalizer,
    ) {}

    /**
     * Process a single axe-core page result, persisting findings and issues
     * then triggering risk and governance recalculation.
     *
     * @param  array{url: string, violations: array<int, array{id: string, impact: string|null, description?: string, helpUrl?: string, tags?: list<string>, nodes: array<int, array{target: array<string>, html?: string, failureSummary?: string}>}>}  $pageResult
     */
    public function handle(Scan $scan, array $pageResult): ScanPage
    {
        $url = $pageResult['url'];
        $violations = $pageResult['violations'] ?? [];
        $detectedAt = Date::now();

        $findings = $this->persistFindings($scan, $url, $violations, $detectedAt);
        $this->normalizeFindings($findings, $scan);

        $page = $this->scanPage->record($scan, $url, $findings->count());

        return $page;
    }

    /**
     * @param  array<int, array{id: string, impact: string|null, description?: string, helpUrl?: string, tags?: list<string>, nodes: array<int, array{target: array<string>, html?: string, failureSummary?: string}>}>  $violations
     * @return Collection<int, Finding>
     */
    private function persistFindings(Scan $scan, string $url, array $violations, \DateTimeInterface $detectedAt): Collection
    {
        $findings = collect();

        foreach ($violations as $violation) {
            $severity = $this->normalizer->resolveImpact($violation['impact'] ?? null);
            $tags = array_map(
                fn (string $tag) => str_starts_with($tag, 'cat.') ? substr($tag, 4) : $tag,
                $violation['tags'] ?? [],
            );

            foreach ($violation['nodes'] as $node) {
                $rawTarget = $node['target'][0] ?? null;
                $elementIdentifier = is_array($rawTarget) ? implode(' > ', $rawTarget) : $rawTarget;
                $fingerprint = sha1($violation['id'].'|'.($elementIdentifier ?? '').'|'.$url);

                try {
                    $finding = Finding::withoutGlobalScope(TenantScope::class)->createOrFirst(
                        ['scan_id' => $scan->id, 'fingerprint' => $fingerprint],
                        [
                            'agency_id' => $scan->agency_id,
                            'property_id' => $scan->property_id,
                            'rule_key' => $violation['id'],
                            'severity' => $severity,
                            'wcag_category' => $this->normalizer->resolveWcagCategory($tags),
                            'wcag_criteria' => $this->normalizer->resolveWcagCriteria($tags),
                            'description' => $violation['description'] ?? null,
                            'tags' => $tags ?: null,
                            'help_url' => $violation['helpUrl'] ?? null,
                            'element_identifier' => $elementIdentifier,
                            'element_html' => $node['html'] ?? null,
                            'page_url' => $url,
                            'message' => $node['failureSummary'] ?? '',
                            'detected_at' => $detectedAt,
                        ]
                    );
                } catch (UniqueConstraintViolationException|\PDOException $e) {
                    if ($e instanceof \PDOException && ($e->errorInfo[1] ?? null) !== 1062) {
                        throw $e;
                    }

                    $finding = Finding::withoutGlobalScope(TenantScope::class)
                        ->where('scan_id', $scan->id)
                        ->where('fingerprint', $fingerprint)
                        ->first();
                }

                $findings->push($finding);
            }
        }

        return $findings;
    }

    /**
     * @param  Collection<int, Finding>  $findings
     */
    private function normalizeFindings(Collection $findings, Scan $scan): void
    {
        $incrementedIssueIds = [];

        foreach ($findings as $finding) {
            $issue = Issue::query()
                ->where('agency_id', $finding->agency_id)
                ->where('property_id', $finding->property_id)
                ->where('rule_key', $finding->rule_key)
                ->where('page_url', $finding->page_url)
                ->whereIn('status', [IssueStatus::Open->value, IssueStatus::InProgress->value])
                ->first();

            if ($issue) {
                $issue->update([
                    'last_detected_at' => $finding->detected_at,
                    'wcag_criteria' => $issue->wcag_criteria ?? $finding->wcag_criteria,
                    'description' => $issue->description ?? $finding->description,
                    'tags' => $issue->tags ?? $finding->tags,
                    'help_url' => $issue->help_url ?? $finding->help_url,
                ]);

                if (! in_array($issue->id, $incrementedIssueIds)) {
                    $issue->incrementOccurrence();
                    $incrementedIssueIds[] = $issue->id;
                }

                $finding->update(['issue_id' => $issue->id]);

                continue;
            }

            $issue = Issue::query()->create([
                'agency_id' => $finding->agency_id,
                'organization_id' => $scan->organization_id,
                'property_id' => $finding->property_id,
                'rule_key' => $finding->rule_key,
                'page_url' => $finding->page_url,
                'severity' => $this->normalizer->mapToIssueSeverity($finding->severity),
                'wcag_category' => $finding->wcag_category,
                'wcag_criteria' => $finding->wcag_criteria,
                'description' => $finding->description,
                'tags' => $finding->tags,
                'help_url' => $finding->help_url,
                'status' => IssueStatus::Open,
                'occurrence_count' => 1,
                'risk_weight' => $this->normalizer->resolveRiskWeight($finding->severity),
                'first_detected_at' => $finding->detected_at,
                'last_detected_at' => $finding->detected_at,
            ]);

            $finding->update(['issue_id' => $issue->id]);
        }
    }
}
