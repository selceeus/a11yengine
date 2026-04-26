<?php

namespace App\Domain\Issues;

use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Scan;

class NormalizeScanFindings
{
    public function handle(Scan $scan): void
    {
        $incrementedIssueIds = [];

        $scan->findings()->each(function (Finding $finding) use (&$incrementedIssueIds): void {
            $this->normalizeFiniding($finding, $incrementedIssueIds);
        });
    }

    private function normalizeFiniding(Finding $finding, array &$incrementedIssueIds): void
    {
        $issue = Issue::query()
            ->where('agency_id', $finding->agency_id)
            ->where('property_id', $finding->property_id)
            ->where('rule_key', $finding->rule_key)
            ->where('page_url', $finding->page_url)
            ->whereIn('status', [
                IssueStatus::Open->value,
                IssueStatus::InProgress->value,
            ])
            ->first();

        if ($issue) {
            $issue->update(['last_detected_at' => $finding->detected_at]);

            if ($finding->issue_id === null && ! in_array($issue->id, $incrementedIssueIds)) {
                $issue->incrementOccurrence();
                $incrementedIssueIds[] = $issue->id;
            }

            $finding->update(['issue_id' => $issue->id]);

            return;
        }

        $issue = Issue::query()->create([
            'agency_id' => $finding->agency_id,
            'organization_id' => $finding->scan->organization_id,
            'property_id' => $finding->property_id,
            'rule_key' => $finding->rule_key,
            'page_url' => $finding->page_url,
            'severity' => $this->mapSeverity($finding->severity),
            'status' => IssueStatus::Open,
            'occurrence_count' => 1,
            'risk_weight' => $this->resolveRiskWeight($finding->severity),
            'first_detected_at' => $finding->detected_at,
            'last_detected_at' => $finding->detected_at,
        ]);

        $finding->update(['issue_id' => $issue->id]);
    }

    private function mapSeverity(FindingSeverity $severity): IssueSeverity
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
}
