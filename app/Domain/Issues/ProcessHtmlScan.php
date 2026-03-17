<?php

namespace App\Domain\Issues;

use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Domain\Risk\RecordRiskSnapshot;
use App\Domain\Scans\ScanPage as ScanPageDomain;
use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class ProcessHtmlScan
{
    public function __construct(
        private readonly ScanPageDomain $scanPage,
        private readonly RecordRiskSnapshot $riskSnapshot,
        private readonly RecordOrganizationRiskSnapshot $orgRiskSnapshot,
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

        $this->riskSnapshot->handle($scan->organization_id);
        $this->orgRiskSnapshot->handle($scan->organization_id);

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
            $severity = $this->mapImpact($violation['impact'] ?? null);
            $tags = array_map(
                fn (string $tag) => str_starts_with($tag, 'cat.') ? substr($tag, 4) : $tag,
                $violation['tags'] ?? [],
            );

            foreach ($violation['nodes'] as $node) {
                $elementIdentifier = $node['target'][0] ?? null;
                $fingerprint = sha1($violation['id'].'|'.($elementIdentifier ?? '').'|'.$url);

                $finding = Finding::withoutGlobalScope(TenantScope::class)->firstOrCreate(
                    ['scan_id' => $scan->id, 'fingerprint' => $fingerprint],
                    [
                        'agency_id' => $scan->agency_id,
                        'property_id' => $scan->property_id,
                        'rule_key' => $violation['id'],
                        'severity' => $severity,
                        'wcag_category' => $this->resolveWcagCategory($tags),
                        'wcag_criteria' => $this->resolveWcagCriteria($tags),
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

                $finding->update(['issue_id' => $issue->id]);

                continue;
            }

            $issue = Issue::query()->create([
                'agency_id' => $finding->agency_id,
                'organization_id' => $scan->organization_id,
                'property_id' => $finding->property_id,
                'rule_key' => $finding->rule_key,
                'page_url' => $finding->page_url,
                'severity' => $this->mapSeverityToIssue($finding->severity),
                'wcag_category' => $finding->wcag_category,
                'wcag_criteria' => $finding->wcag_criteria,
                'description' => $finding->description,
                'tags' => $finding->tags,
                'help_url' => $finding->help_url,
                'status' => IssueStatus::Open,
                'occurrence_count' => 1,
                'risk_weight' => $this->resolveRiskWeight($finding->severity),
                'first_detected_at' => $finding->detected_at,
                'last_detected_at' => $finding->detected_at,
            ]);

            $finding->update(['issue_id' => $issue->id]);
        }
    }

    private function mapImpact(?string $impact): FindingSeverity
    {
        return match ($impact) {
            'critical' => FindingSeverity::CRITICAL,
            'serious' => FindingSeverity::SERIOUS,
            'moderate' => FindingSeverity::MODERATE,
            'minor' => FindingSeverity::MINOR,
            default => FindingSeverity::INFO,
        };
    }

    private function mapSeverityToIssue(FindingSeverity $severity): IssueSeverity
    {
        return match ($severity) {
            FindingSeverity::CRITICAL => IssueSeverity::Critical,
            FindingSeverity::SERIOUS => IssueSeverity::High,
            FindingSeverity::MODERATE => IssueSeverity::Medium,
            FindingSeverity::MINOR => IssueSeverity::Low,
            FindingSeverity::INFO => IssueSeverity::Low,
        };
    }

    private function resolveRiskWeight(FindingSeverity $severity): int
    {
        return match ($severity) {
            FindingSeverity::CRITICAL => 100,
            FindingSeverity::SERIOUS => 75,
            FindingSeverity::MODERATE => 50,
            FindingSeverity::MINOR => 25,
            FindingSeverity::INFO => 10,
        };
    }

    /**
     * Extracts the WCAG success criterion and conformance level from axe tags.
     * e.g. tags ['wcag2aa', 'wcag143'] → '1.4.3 AA'
     *
     * @param  list<string>  $tags
     */
    private function resolveWcagCriteria(array $tags): ?string
    {
        $criterion = null;

        foreach ($tags as $tag) {
            if (preg_match('/^wcag(\d)(\d)(\d+)$/', $tag, $matches)) {
                $criterion = $matches[1].'.'.$matches[2].'.'.$matches[3];
                break;
            }
        }

        if ($criterion === null) {
            return null;
        }

        $level = null;

        foreach ($tags as $tag) {
            if (preg_match('/^wcag\d+aaa$/i', $tag)) {
                $level = 'AAA';
                break;
            }

            if (preg_match('/^wcag\d+aa$/i', $tag)) {
                $level = 'AA';
                break;
            }

            if (preg_match('/^wcag\d+a$/i', $tag)) {
                $level = 'A';
                break;
            }
        }

        return $level !== null ? $criterion.' '.$level : $criterion;
    }

    /**
     * @param  list<string>  $tags
     */
    private function resolveWcagCategory(array $tags): string
    {
        $prefixes = [
            'wcag1' => 'perceivable',
            'wcag2' => 'operable',
            'wcag3' => 'understandable',
            'wcag4' => 'robust',
        ];

        foreach ($tags as $tag) {
            foreach ($prefixes as $prefix => $category) {
                if (str_starts_with($tag, $prefix)) {
                    return $category;
                }
            }
        }

        return 'best-practice';
    }
}
