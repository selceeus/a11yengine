<?php

namespace App\Domain\Issues;

use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;

class IssueNormalizer
{
    /**
     * Maps axe-core WCAG tag prefixes to the four WCAG principles.
     *
     * Axe-core tags follow the pattern 'wcag{principle}{level}', e.g. 'wcag1aa', 'wcag2a'.
     * We match on the principle digit to determine the category.
     *
     * @var array<string, string>
     */
    private const WCAG_TAG_PREFIXES = [
        'wcag1' => 'perceivable',
        'wcag2' => 'operable',
        'wcag3' => 'understandable',
        'wcag4' => 'robust',
    ];

    /**
     * Risk weights keyed by FindingSeverity, used to express relative priority of an issue.
     *
     * @var array<string, int>
     */
    private const RISK_WEIGHTS = [
        'critical' => 100,
        'serious' => 75,
        'moderate' => 50,
        'minor' => 25,
        'info' => 10,
    ];

    /**
     * Normalise a single axe-core violation into a structured Issues-format array.
     *
     * @param  array{id: string, impact: string|null, tags?: list<string>, nodes: list<array{target: list<string>, html?: string, failureSummary?: string}>}  $violation
     * @return array{
     *     rule_id: string,
     *     wcag_category: string,
     *     severity: FindingSeverity,
     *     issue_severity: IssueSeverity,
     *     risk_weight: int,
     *     elements: list<array{identifier: string, html: string|null, failure_summary: string|null}>
     * }
     */
    public function normalize(array $violation): array
    {
        $severity = $this->resolveImpact($violation['impact'] ?? null);

        return [
            'rule_id' => $violation['id'],
            'wcag_category' => $this->resolveWcagCategory($violation['tags'] ?? []),
            'severity' => $severity,
            'issue_severity' => $this->mapToIssueSeverity($severity),
            'risk_weight' => $this->resolveRiskWeight($severity),
            'elements' => $this->extractElements($violation['nodes'] ?? []),
        ];
    }

    /**
     * Resolve the WCAG principle category from axe-core rule tags.
     *
     * Iterates the violation's tag list and returns the first matching WCAG principle.
     * Falls back to 'best-practice' when no WCAG tag is present (e.g. best-practice rules).
     *
     * @param  list<string>  $tags
     */
    private function resolveWcagCategory(array $tags): string
    {
        foreach ($tags as $tag) {
            foreach (self::WCAG_TAG_PREFIXES as $prefix => $category) {
                if (str_starts_with($tag, $prefix)) {
                    return $category;
                }
            }
        }

        return 'best-practice';
    }

    /**
     * Map an axe-core impact string to a FindingSeverity enum value.
     */
    private function resolveImpact(?string $impact): FindingSeverity
    {
        return match ($impact) {
            'critical' => FindingSeverity::CRITICAL,
            'serious' => FindingSeverity::SERIOUS,
            'moderate' => FindingSeverity::MODERATE,
            'minor' => FindingSeverity::MINOR,
            default => FindingSeverity::INFO,
        };
    }

    /**
     * Map a FindingSeverity (axe-core granularity) to the coarser IssueSeverity level.
     */
    private function mapToIssueSeverity(FindingSeverity $severity): IssueSeverity
    {
        return match ($severity) {
            FindingSeverity::CRITICAL => IssueSeverity::Critical,
            FindingSeverity::SERIOUS => IssueSeverity::High,
            FindingSeverity::MODERATE => IssueSeverity::Medium,
            FindingSeverity::MINOR => IssueSeverity::Low,
            FindingSeverity::INFO => IssueSeverity::Low,
        };
    }

    /**
     * Resolve the numeric risk weight for a given severity level.
     */
    private function resolveRiskWeight(FindingSeverity $severity): int
    {
        return self::RISK_WEIGHTS[$severity->value] ?? 10;
    }

    /**
     * Extract per-element metadata from axe-core violation nodes.
     *
     * Each node represents a single affected element on the page. We capture:
     * - identifier: the first CSS selector from the target array
     * - html:        the serialised outer HTML of the element (may be null)
     * - failure_summary: the human-readable remediation hint from axe-core
     *
     * @param  list<array{target: list<string>, html?: string, failureSummary?: string}>  $nodes
     * @return list<array{identifier: string, html: string|null, failure_summary: string|null}>
     */
    private function extractElements(array $nodes): array
    {
        $elements = [];

        foreach ($nodes as $node) {
            $elements[] = [
                'identifier' => $node['target'][0] ?? '',
                'html' => $node['html'] ?? null,
                'failure_summary' => $node['failureSummary'] ?? null,
            ];
        }

        return $elements;
    }
}
